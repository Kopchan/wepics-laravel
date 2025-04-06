<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\ImageExtension;
use App\Enums\SortType;
use App\Exceptions\ApiDebugException;
use App\Exceptions\ApiException;
use App\Http\Requests\AlbumImagesRequest;
use App\Http\Requests\AlbumCreateRequest;
use App\Http\Requests\UploadRequest;
use App\Http\Resources\ImageLinkResource;
use App\Http\Resources\ImageResource;
use App\Models\Album;
use App\Models\Image;
use App\Models\ImageDuplica;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Laravel\Facades\Image as Intervention;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public static function indexingImages(Album $album)
    {
        // TODO: Перейти на свою индексацию через glob для быстрой и одновременной индексации картинок и папок (мб всех файлов)
        // Путь к альбому
        $path = Storage::path("images$album->path");

        // Получение файлов альбома
        //$files = File::files($path); // FIXME: медленное и боится символических ссылок в отличии от Storage::
        $files = array_filter(glob("$path*", GLOB_MARK), fn ($path) => !in_array($path[-1], ['/', '\\']));

        // Получение разрешённых расширений файлов
        $allowedExtensions = array_column(ImageExtension::cases(), 'value');

        // Получение имеющихся картинок в БД
        //$imagesInDB = $album->images()->with('duplicas')->get()->toArray();
        $imagesInDB = $album->images()->with('duplicas')->get();

        // Объединение картинок и их дубликатов в единый массив
        $images = $imagesInDB->flatMap(function ($image) {
            $origImage = $image->toArray();
            return array_merge(
                [$origImage],
                $image->duplicas->map(fn ($duplica) =>
                    array_merge($origImage, [
                        'name' => $duplica->name,
                        'origName' => $image->name,
                    ]
                )
            )->toArray());
        })->toArray();

        $imagesNotFinded = [];

        // Массивы для поиска
        $imagesNames  = array_column($images, 'name');
        $imagesHashes = array_column($images, 'hash');

        $errors = [];

        // Время начала проходов
        $start = microtime(true);

        // Проход по файлам
        foreach ($files as $i => $file) {
            $i_start = microtime(true);

            // Получение времени
            if (microtime(true) - $start > 55)
                throw new ApiDebugException([
                    'current' => $i,
                    'total' => count($files),
                ]);

            try {
                $name = basename($file);

                // Проверка наличия в БД картинки по названию, если есть — пропуск
                // FIXME: А если юзер переименует две картинки наоборот?
                $key = array_search($name, $imagesNames);
                if ($key !== false)
                    continue;

                // Отсекание не-картинок по расширению файла
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (!in_array($extension, $allowedExtensions))
                    continue;

                // Получение хеша картинки (прожорливое к скорости чтения)
                $hash = hash_file('xxh3', $file);

                // Проверка наличия в БД картинки по хешу, если есть — проверяем в ФС
                $key = array_search($hash, $imagesHashes);
                if ($key !== false) {
                    // Полное имя картинки-оригинала (из БД)
                    $imageFullName = Storage::path('images' . $album->path . $images[$key]['name']);

                    // Проверка наличие картинки-оригинала в ФС ...
                    $filesKey = array_search($imageFullName, $files);
                    if ($filesKey === false) {
                        // Если нету — переименовываем в БД
                        Image
                            ::where('id', $images[$key]['id'])
                            ->update(['name' => $name]);
                    } else {
                        // Если есть — создаём дубликат в БД (чтобы в следующий раз не создавать хеш)
                        ImageDuplica::create([
                            'image_id' => $images[$key]['id'],
                            'name' => $name,
                        ]);

                        // Записываем в массивы чтобы не наткнутся на повторы снова
                        $images[$key]['duplicas'][] = ['name' => $name];
                        $images[] = array_merge($images[$key], [
                            'name' => $name,
                            'origName' => $images[$key]['name']
                        ]);
                    }
                    // И пропуск создания картинки в БД
                    continue;
                }

                // Получение размеров картинки, если нет размеров (не получили) — пропуск
                $sizes = getimagesize($file);
                if (!$sizes) continue;

                // Создание в БД записи
                $imageModel = Image::create([
                    'name' => $name,
                    'hash' => $hash,
                    'date' => Carbon::createFromTimestamp(File::lastModified($file)),
                    'size' => File::size($file),
                    'width'  => $sizes[0],
                    'height' => $sizes[1],
                    'album_id' => $album->id,
                ]);

                // Обновление массивов картинок альбома
                $images[] = $imageModel->toArray();
                $imagesNames[] = $name;
                $imagesHashes[] = $hash;
            }
            catch (\Exception $ex) {
                if ($ex instanceof ApiDebugException)
                    throw $ex;

                $errors[] = $ex;
            }
        }

        // Удаление не найденных картинок в БД
        //Image::destroy(array_column($images, 'id')); // FIXME
        return ['errors' => $errors];
    }

    public function upload(UploadRequest $request, $albumHash)
    {
        $album = Album::getByHash($albumHash);
        $files = $request->file('images');
        $path = "images$album->path";
        $allowedExts = array_column(ImageExtension::cases(), 'value');
        $allowedExitsImploded = implode(',', $allowedExts);

        $responses = [];
        foreach ($files as $file) {
            $fileName = $file->getClientOriginalName();
            $fileExt  = $file->extension();

            // Валидация файла
            $validator = Validator::make(['file' => $file], [
                'file' => ['mimes:'. $allowedExitsImploded],
            ]);
            if ($validator->fails()) {
                // Сохранение плохого ответа API
                $responses['errored'][] = [
                    'name'    => $fileName,
                    'message' => $validator->errors(),
                ];
                continue;
            }

            // Проверка существования того же файла
            $imageHash = hash_file('xxh3', $file);

            $image = Image
                ::where('album_id', $album->id)
                ->where('hash', $imageHash)
                ->first();

            if ($image) {
                // Сохранение плохого ответа API
                $responses['errored'][] = [
                    'name'    => $fileName,
                    'message' => "Image with this hash \"$imageHash\" already exist in this album",
                ];
                continue;
            }

            // Наименование повторяющихся
            $fileNameNoExt = basename($fileName, ".$fileExt");
            $num = 1;
            while (Storage::exists("$path$fileName")) {
                $fileName = "$fileNameNoExt ($num).$fileExt";
                $num++;
            }

            // Сохранение файла в хранилище
            $file->storeAs($path,$fileName);

            $sizes = getimagesize(Storage::path($path.$fileName));

            // Сохранение записи в БД
            $imageDB = Image::create([
                'name' => $fileName,
                'hash' => $imageHash,
                'date' => Carbon::createFromTimestamp(Storage::lastModified($path.$fileName)),
                'size' => Storage::size($path.$fileName),
                'width'  => $sizes[0],
                'height' => $sizes[1],
                'album_id' => $album->id,
            ]);

            // Сохранение успешного ответа API
            $responses['successful'][] = ImageResource::make($imageDB);
        }
        return response($responses);
    }

    public function showAll(AlbumImagesRequest $request, $albumHash)
    {
        $album = Album::getByHash($albumHash);
        $user = $request->user();
        $accessLevel = $album->getAccessLevelCached($user);
        if ($accessLevel == AccessLevel::None)
            throw new ApiException(403, 'Forbidden for you');

        $cacheKey = "albumIndexing:hash=$albumHash";
        if (!Cache::get($cacheKey)) {
            ImageController::indexingImages($album);
            Cache::put($cacheKey, true, 43200);
        };

        $searchedTags = null;
        $tagsString = $request->tags;
        if ($tagsString)
            $searchedTags = explode(',', $tagsString);

        $allowedSorts = array_column(SortType::cases(), 'value');
        $sortType = $request->sort ?? $allowedSorts[0];

        $sortDirection = $request->has('reverse') ? 'DESC' : 'ASC';
      //$naturalSort = "udf_NaturalSortFormat(name, 10, '.') $sortDirection";
        $naturalSort = "natural_sort_key $sortDirection";
        $orderByRaw = match ($sortType) {
            'name'  =>                                "$naturalSort",
            'ratio' => "width / height $sortDirection, $naturalSort",
            default =>      "$sortType $sortDirection, $naturalSort",
        };
        $limit = intval($request->limit);
        if (!$limit)
            $limit = 30;

        $isNested = $request->has('nested');
        $isForceNested = $request->nested == 'force'; // TODO: индексировать картинки и альбомы при рекурсивно усиленному

        $albumIds = [$album->id];
        if ($isNested) {
            $descendants = $album->descendants()->get();
            $isNested = !!$descendants->count();
        }


        if ($isNested) {

            foreach ($descendants as $descendant) {
                switch ($descendant->getAccessLevelCached($user)) {
                    case AccessLevel::None:
                        break;

                    case AccessLevel::AsAllowedUser;
                    case AccessLevel::AsAdmin:
                        $descendant['sign'] = $descendant->getSign($user);
                        $albumIds[] = $descendant->id;
                        break;

                    case AccessLevel::AsGuest:
                        $albumIds[] = $descendant->id;
                        break;
                }
            }
        }

        $dbQuery = $imagesFromDB = Image
            ::whereIn('album_id', $albumIds)
            ->with('tags', 'reactions')
            ->orderByRaw($orderByRaw);

        $imagesFromDB = !$searchedTags
            ? $dbQuery->paginate($limit)
            : $dbQuery->withAllTags($searchedTags)->paginate($limit);

        if ($isNested)
            foreach ($imagesFromDB as $image) {
                $currentImageAlbum = $descendants?->where('id', $image->album_id)->first();
                $albumInfo = [];
                if ($currentImageAlbum) {
                    $albumInfo['hash'] = $currentImageAlbum->hash;
                    $albumInfo['name'] = $currentImageAlbum->name;
                }
                if ($currentImageAlbum?->sign)
                    $albumInfo['sign'] = $currentImageAlbum->sign;

                $image->customAlbum = $albumInfo;
            }

        $response = [
            'page'     => $imagesFromDB->currentPage(),
            'per_page' => $imagesFromDB->perPage(),
            'total'    => $imagesFromDB->total(),
            'pictures' => !$isNested
                ? ImageResource    ::collection($imagesFromDB->items())
                : ImageLinkResource::collection($imagesFromDB->items()),
        ];

        if (
            !$isNested
            && !$album->guest_allow
            && $user
            && $accessLevel != AccessLevel::AsGuest
        )
            $response['sign'] = $album->getSign($user);

            
        //dd(!$isNested, !$album->guest_allow, !!$user, $accessLevel != AccessLevel::AsGuest);

        //dd($user, $accessLevel, $accessLevel != AccessLevel::AsGuest, $response, $imagesFromDB->toArray());

        return response($response);
    }

    public function thumb($albumHash, $imageHash, $orientation, $size)
    {
        // TODO: обрабатывать запрос на превью бОльшего размера, чем оригинала
        // Проверка доступа по токену в ссылке
        $sign = request()->sign;
        if (
            (Album::getAccessLevelCachedByHash($albumHash, null) === AccessLevel::None) &&
            !($sign && Album::checkSignStatic($albumHash, $sign))
        ) {
            // Проверка доступа по токену в заголовках
            $image = Image::getByHash($albumHash, $imageHash);
            if (Album::getAccessLevelCachedByHash($albumHash, request()->user()) === AccessLevel::None)
                throw new ApiException(403, 'Forbidden for you');
        }

        // Проверка наличия превью в файлах
        $thumbPath = "thumbs/$imageHash-$orientation$size.webp";
        if (!Storage::exists($thumbPath)) {
            // Проверка запрашиваемого размера и редирект, если не прошло
            $askedSize = $size;
            $allowedSizes = [144, 240, 360, 480, 720, 1080];
            $allowSize = false;
            foreach ($allowedSizes as $allowedSize) {
                if ($size <= $allowedSize) {
                    $size = $allowedSize;
                    $allowSize = true;
                    break;
                }
            }
            if (!$allowSize) $size = $allowedSizes[count($allowedSizes)-1];
            if ($askedSize != $size)
                return redirect()->route('get.image.thumb', [
                    $albumHash, $imageHash, $orientation, $size
                ])->header('Cache-Control', ['max-age=86400', 'private']);;

            // Проверка наличия превью в файлах x2
            $thumbPath = "thumbs/$imageHash-$orientation$size.webp";
            if (!Storage::exists($thumbPath)) {
                // Создание превью
                $image = $image ?? Image::getByHash($albumHash, $imageHash);

                $imagePath = Storage::path('images'. $image->album->path . $image->name);

                $thumb = Intervention::read($imagePath);

                if ($orientation == 'w')
                    $thumb->scale(width: $size);
                else
                    $thumb->scale(height: $size);

                if (!Storage::exists('thumbs'))
                    Storage::makeDirectory('thumbs');

                $thumb->toWebp(90)->save(Storage::path($thumbPath));
                unset($thumb);
            }
        }
        return response()->file(Storage::path($thumbPath), ['Cache-Control' => ['max-age=86400', 'private']]);
    }

    public function info($albumHash, $imageHash)
    {
        $image = Image::getByHash($albumHash, $imageHash);
        if (!$image->album->getAccessLevelCachedh(request()->user()))
            throw new ApiException(403, 'Forbidden for you');

        return response(ImageResource::make($image));
    }

    public function orig($albumHash, $imageHash)
    {
        // Проверка доступа по токену в ссылке
        $sign = request()->sign;
        if (
            (Album::getAccessLevelCachedByHash($albumHash, null) === AccessLevel::None) &&
            !($sign && Album::checkSignStatic($albumHash, $sign))
        ) {
            // Проверка доступа по токену в заголовках
            $image = Image::getByHash($albumHash, $imageHash);
            if ($image->album->getAccessLevelCached(request()->user()) === AccessLevel::None)
                throw new ApiException(403, 'Forbidden for you');
        }
        $image = $image ?? Image::getByHash($albumHash, $imageHash);
        $path = Storage::path('images'. $image->album->path . $image->name);
        ob_end_clean();
        return response()->file($path);
    }

    public function download($albumHash, $imageHash)
    {
        // Проверка доступа по токену в ссылке
        $sign = request()->sign;
        if (
            (Album::getAccessLevelCachedByHash($albumHash, null) === AccessLevel::None) &&
            !($sign && Album::checkSignStatic($albumHash, $sign))
        ) {
            // Проверка доступа по токену в заголовках
            $image = Image::getByHash($albumHash, $imageHash);
            if (!$image->album->getAccessLevelCached(request()->user()) === AccessLevel::None)
                throw new ApiException(403, 'Forbidden for you');
        }
        $image = $image ?? Image::getByHash($albumHash, $imageHash);
        $path = Storage::path('images'. $image->album->path . $image->name);
        ob_end_clean();
        return response()->download($path, $image->name);
    }

    public function rename(AlbumCreateRequest $request, $albumHash, $imageHash)
    {
        $image = Image::getByHash($albumHash, $imageHash);
        $imageExt = pathinfo($image->name, PATHINFO_EXTENSION);
        $newName = $request->name;

        $oldLocalPath = 'images'. $image->album->path . $image->name;
        $newPath = $image->album->path ."$newName.$imageExt";
        $newLocalPath = "images$newPath";
        if (Storage::exists($newPath))
            throw new ApiException(409, 'Album with this name already exist');

        Storage::move($oldLocalPath, $newLocalPath);
        $image->update([
            'name' => basename($newPath),
            'path' => "$newPath",
        ]);
        return response(null, 204);
    }

    public function delete($albumHash, $imageHash)
    {
        $image = Image::getByHash($albumHash, $imageHash);

        $imagePath = 'images'. $image->album->path . $image->name;
        Storage::delete($imagePath);

        $thumbPath = "thumbs/$image->hash-*";
        File::delete(File::glob(Storage::path($thumbPath)));

        $image->delete();

        return response(null, 204);
    }
}
