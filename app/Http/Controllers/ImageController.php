<?php

namespace App\Http\Controllers;

use App\Cacheables\SpaceInfo;
use App\Enums\AccessLevel;
use App\Enums\ImageExtension;
use App\Enums\MediaType;
use App\Enums\SortType;
use App\Exceptions\ApiDebugException;
use App\Exceptions\ApiException;
use App\Helpers\StreamHelper;
use App\Http\Requests\AlbumImagesRequest;
use App\Http\Requests\AlbumCreateRequest;
use App\Http\Requests\UploadRequest;
use App\Http\Resources\ImageLinkResource;
use App\Http\Resources\ImageResource;
use App\Jobs\GeneratePreviewVideo;
use App\Models\Album;
use App\Models\Image;
use App\Models\ImageDuplica;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Intervention\Image\Laravel\Facades\Image as Intervention;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;

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
            catch (Exception $ex) {
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
        $user = $request->user();
        $album = Album::getByHash($albumHash);
        $files = $request->file('images');

        // Получаем сколько сейчас занято на диске, если достигли предела - выводим ошибку
        $spaceInfo = SpaceInfo::getCached();
        if ($spaceInfo->isUploadDisabled)
            throw new ApiException(400, 'Server in read-only mode');

        // Получаем сколько сейчас весят пользовательское медиа и какой лимит по загрузкам
        $currentStorageSize = $user->quotaUsed();
        $maxStorageSize = $user->quotaTotal();

        // Путь альбома для сохранения
        $pathToSave = "users/$user->id/$album->path";

        // Разрешённые расширения
        $allowedImageExtensions = config('setups.allowed_image_extensions');
        $allowedVideoExtensions = config('setups.allowed_video_extensions');
        $allowedAudioExtensions = config('setups.allowed_audio_extensions');

        // Массивы для ответа
        $errored    = [];
        $successful = [];

        // Драйвер FFProbe для получения информации из видео и аудио
        $probe = FFProbe::create();

        $responses = [];
        foreach ($files as $key => $pictureInRequests) {
            $file = $request->file("images.$key.file");
            $date = $pictureInRequests['date'] ?? now();
            $filename = $pictureInRequests['name'] ?? $file->getClientOriginalName();

            $extReq = pathinfo($filename, PATHINFO_EXTENSION);
            $extReal = $file->guessExtension() ?? $file->extension();
            if ($extReal != null && $extReal != $extReq)
                $filename .= ".$extReal";

            // Пропуск файла с не найденного в разрешённых расширению, иначе определяется тип
            $extDown = strtolower($extReal);
            if (in_array($extDown, $allowedImageExtensions))
                $type = 'image';
            else
            if (in_array($extDown, $allowedVideoExtensions))
                $type = 'video';
            else
            if (in_array($extDown, $allowedAudioExtensions))
                $type = 'audio';
            else {
                $errored[] = [
                    'name'    => $filename,
                    'code'    => 1409,
                    'message' => "\".$extDown\" not support/allowed",
                ];
                continue;
            }

            // Проверяем, превышает ли размер лимит
            $filesize = $file->getSize();
            if ($currentStorageSize + $filesize >= $maxStorageSize) {
                $errored[] = [
                    'name'    => $filename,
                    'code'    => 1410,
                    'message' => 'Storage limit reached',
                    'debug1' => $currentStorageSize + $filesize >= $maxStorageSize,
                    'debug2' => $currentStorageSize,
                    'debug3' => $filesize,
                    'debug4' => $maxStorageSize,
                ];
                continue;
            }

            // Проверка, существует ли картинка с таким хешем и пропускаем если да
            $tmpPath = $file->getRealPath();
            $hash = hash_file('xxh3', $tmpPath);
            $existedImageByHash = Image
                ::where('album_id', $album->id)
                ->where('hash', $hash)
                ->first();
            if ($existedImageByHash) {
                $errored[] = [
                    'name' => $filename,
                    'code' => 1203,
                    'message' => 'Already exist with this hash',
                    'image' => ImageResource::make($existedImageByHash),
                ];
                continue;
            }

            // Сохранение в другом названии, если есть какой-то запрещённый символ в названии
            if (!preg_match('~^[^/\\\\:*?"<>|]+$~u', $filename))
                $filename = $hash . ".$extReal";

            // Проверка, существует ли картинка с таким именем и модифицируем имя счётчиком если да
            $counter = 1;
            $extension      = pathinfo($filename, PATHINFO_EXTENSION);
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $filenameValid = $filename;
            while (Image
                ::where('album_id', $album->id)
                ->where('name', $filenameValid)
                ->exists()
            ) {
                $counter++;
                $filenameValid = "$nameWithoutExt ($counter).$extension";
            }

            // Сохранение файла в хранилище
            $file->storeAs($pathToSave, $filenameValid);

            // Получение размеров картинки, если нет размеров (не получили) — пропуск
            if ($type === 'image') {
                $sizes = getimagesize($file); // TODO: на перевёрнутых JPG даёт те же размеры
                if (!$sizes) {
                    $errored[] = [
                        'name' => $filename,
                        'code' => 1413,
                        'message' => 'Cannot grab size of image',
                        'image' => ImageResource::make($existedImageByHash),
                    ];
                    continue;
                }
            }

            if ($type !== 'audio')
                $probeInfo = $probe->streams($file)->videos()->first();

            else
                $probeInfo = $probe->streams($file)->audios()->first();

            $steamContentFields = [];

            if ($type === 'image' && ($probeInfo?->get('duration_ts') ?? 0) > 1)
                $type = 'imageAnimated';

            if ($type !== 'image') {
                if (!($probeInfo?->get('duration_ts'))) {
                    $errored[] = [
                        'name' => $filename,
                        'code' => 1413,
                        'message' => 'Cannot grab duration_ts',
                        'image' => ImageResource::make($existedImageByHash),
                    ];
                    continue;
                }
                $steamContentFields['codec_name'] = $probeInfo->get('codec_name');

                $number = $probeInfo->get('duration');
                if (!str_contains($number, '.')) $number .= '.000';
                [$intPart, $decimalPart] = explode('.', $number, 2);
                $decimalPart = substr($decimalPart . '000', 0, 3);
                $steamContentFields['duration_ms'] = (int)($intPart . $decimalPart);

                if ($type !== 'audio') {
                    $sizes = [
                        $probeInfo->get('width'),
                        $probeInfo->get('height')
                    ];

                    $framerate = array_map('intval',
                        explode('/', $probeInfo->get('avg_frame_rate'))
                    );
                    $steamContentFields['avg_frame_rate_num'] = $framerate[0];
                    $steamContentFields['avg_frame_rate_den'] = $framerate[1];
                    $steamContentFields['frame_count'] = (int)$probeInfo->get('nb_frames');
                }
                else {
                    $sizes = [500, 500]; // TODO: Читать из обложки аудио
                }
            }

            // Сохранение записи в БД
            $imageDB = Image::create([
                'album_id' => $album->id,
                'name' => $filenameValid,
                'type' => $type,
                'hash' => $hash,
                'date' => $date,
                'size' => $filesize,
                'width'  => $sizes[0],
                'height' => $sizes[1],
                ...$steamContentFields,
            ]);

            // Сохранение успешного ответа API
            $successful[] = ImageResource::make($imageDB);
        }
        return response([
            'sign' => $album->getSign($user),
            'successful' => $successful,
            'errored' => $errored,
        ]);
    }

    public function showAll(AlbumImagesRequest $request, $albumHash)
    {
        $album = Album::getByHashOrAlias($albumHash);
        $user = $request->user();
        $accessLevel = $album->getAccessLevelCached($user);
        if ($accessLevel == AccessLevel::None)
            throw new ApiException(403, 'Forbidden for you');

        //$cacheKey = "albumIndexing:hash=$albumHash";
        //if (!Cache::get($cacheKey)) {
        //    ImageController::indexingImages($album);
        //    Cache::put($cacheKey, true, 43200);
        //};

        $searchedTags = null;
        $tagsString = $request->tags;
        if ($tagsString)
            $searchedTags = explode(',', $tagsString);

        $sortType = $request->sort ?? SortType::values()[0];

        $seed = $request->seed ?? (
            $sortType === 'random'
            ? mt_rand(100_000, 999_999)
            : null
        );

        $sortDirection = $request->has('reverse') ? 'DESC' : 'ASC';
      //$naturalSort = "udf_NaturalSortFormat(name, 10, '.') $sortDirection";
        $naturalSort = "natural_sort_key";
        $orderByRaw = match ($sortType) {
            'random'     => 'RAND('.DB::getPdo()->quote($seed).')',
            'reacts'     => "reactions_count",
            'ratio'      => "width / height",
            'square'     => "ABS(GREATEST(width, height) / LEAST(width, height) - 1)",
            'frames'     => "frames_count IS NULL, frames_count",
            'duration'   => "duration_ms IS NULL, duration_ms",
            'framerate'  => "avg_frame_rate_den IS NULL, avg_frame_rate_num / avg_frame_rate_den",
            'bitrate'    => "duration_ms IS NULL, size * 8 / duration_ms * 1000",
            default      => $sortType,
        };
        $orderByRaw = match ($sortType) {
            'name'  => "$naturalSort $sortDirection",
            default => "$orderByRaw $sortDirection, $naturalSort",
        };

        $limit = intval($request->limit);
        if (!$limit)
            $limit = 30;

        $types = [];
        if ($request->types) {
            foreach ($request->types as $type) {
                $types[] = match ($type) {
                    MediaType::Image->value => 'image',
                    MediaType::Video->value => 'video',
                    MediaType::Audio->value => 'audio',
                    MediaType::ImageAnimated->value => 'imageAnimated',
                };
            }
        }

        $isNested = $request->has('nested');
        $isForceNested = $request->nested == 'force'; // TODO: индексировать картинки и альбомы при рекурсивно усиленному

        $albumIds = [$album->id];
        if ($isNested) {
            $descendants = $album->descendants()->get();
            $isNested = !!$descendants->count();
        }

        $deniedAlbumIds = [];
        if ($isNested) {
            foreach ($descendants as $descendant) {
                if (in_array($descendant->parent_album_id, $deniedAlbumIds)) {
                    $deniedAlbumIds[] = $descendant->id; // Текущий тоже недоступен
                    continue;
                }

                switch ($descendant->getAccessLevelCached($user)) {
                    case AccessLevel::None:
                        $deniedAlbumIds[] = $descendant->id;
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

        $dbQuery = Image
            ::whereIn('album_id', $albumIds)
            ->with('tags', 'reactions')
            ->orderByRaw($orderByRaw);

        if ($sortType === 'reacts')
            $dbQuery->withCount('reactions');

        if (count($types))
            $dbQuery->whereIn('type', $types);

        //if ($isNested)
        //    $dbQuery->with('album');

        $imagesFromDB = !$searchedTags
            ? $dbQuery->paginate($limit)
            : $dbQuery->withAllTags($searchedTags)->paginate($limit);

        if ($isNested)
            foreach ($imagesFromDB as $image) {
                $currentImageAlbum = $descendants?->where('id', $image->album_id)->first();
                if (!$currentImageAlbum)
                    continue;

                $albumInfo = [
                    'name' => $currentImageAlbum->name,
                    'hash' => $currentImageAlbum->hash,
                ];

                if ($currentImageAlbum?->alias)
                    $albumInfo['alias'] = $currentImageAlbum->alias;

                if ($currentImageAlbum?->sign)
                    $albumInfo['sign'] = $currentImageAlbum->sign;

                if ($currentImageAlbum?->age_rating_id)
                    $albumInfo['ratingId'] = $currentImageAlbum->age_rating_id;

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

        if ($seed)
            $response['seed'] = $seed;

        if (
            !$isNested
            && !$album->guest_allow
            && $user
            && $accessLevel != AccessLevel::AsGuest
        )
            $response['sign'] = $album->getSign($user);

        return response($response);
    }

    public function thumb($albumHash, $imageHash, $orientation = null, $size = null, $animated = null)
    {
        // TODO: обрабатывать запрос на превью бОльшего размера, чем оригинала
        // Проверка доступа по токену в ссылке
        $sign = request()->sign;
        if (
            (Album::getAccessLevelCachedByHash($albumHash, null) === AccessLevel::None) &&
            !($sign && Album::checkSignStatic($albumHash, $sign))
        ) {
            // Проверка доступа по токену в заголовках
            $image = Image::getByHashOrAlias($albumHash, $imageHash);
            if (Album::getAccessLevelCachedByHash($albumHash, request()->user()) === AccessLevel::None)
                throw new ApiException(403, 'Forbidden for you');
        }

        // Проверка наличия превью в файлах
        $dirname = "thumbs/{$orientation}{$size}{$animated}";
        $thumbPath = "$dirname/$imageHash" . (!$animated ? '.webp' : '.mp4');
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
                    $albumHash, $imageHash, $orientation, $size, $animated
                ])->header('Cache-Control', ['max-age=86400', 'private']);

            // Создание превью
            $image = $image ?? Image::getByHashOrAlias($albumHash, $imageHash);
            $type = $image->type;

            if ($animated && ($type !== 'imageAnimated' && $type !== 'video'))
                return redirect()->route('get.image.thumb', [
                    $albumHash, $imageHash, $orientation, $size
                ])->header('Cache-Control', ['max-age=86400', 'private']);

            $mediaPath = Storage::path('images'. $image->album->path . $image->name);

            if ($type === 'image')
                $imagePath = $mediaPath;
            else if ($type === 'video' || $type === 'imageAnimated') {
                if (!$animated) {
                    $framePath = Storage::path("thumbs/frames/$image->hash.png");

                    // Использование оригинального превью/кадра видео
                    if (file_exists($framePath))
                        $imagePath = $framePath;
                    else
                        $imagePath = StreamHelper::extractPreview($mediaPath, $image);
                }
            }
            else
                throw new ApiException(500, "Media type \"$type\" not supported");

            if ($animated) {
                // Создание превью как mp4 видео
                GeneratePreviewVideo::dispatchSync($image, $mediaPath, $thumbPath, $orientation, $size);
            }
            else {
                // Создание превью как webp картинку
                $thumb = Intervention::read($imagePath);

                switch ($orientation) {
                    case 'w':
                        $thumb->scaleDown(width: $size);
                        break;
                    case 'h':
                        $thumb->scaleDown(height: $size);
                        break;
                    default:
                        $thumb->coverDown($size, $size);
                        break;
                }

                if (!Storage::exists($dirname))
                    Storage::makeDirectory($dirname);

                $thumb->toWebp(90)->save(Storage::path($thumbPath));
                unset($thumb);
            }
        }
        else
        if ($animated && Storage::fileSize($thumbPath) < 1) {
            return response([
                'message' => 'Pending create video preview'
            ], 202);
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
            $image = Image::getByHashOrAlias($albumHash, $imageHash);
            if ($image->album->getAccessLevelCached(request()->user()) === AccessLevel::None)
                throw new ApiException(403, 'Forbidden for you');
        }
        $image ??= Image::getByHashOrAlias($albumHash, $imageHash);
        $path = Storage::path('images'. $image->album->path . $image->name);
        //dd(base64url_encode(hash_file('xxh3', $path, true)));
        //ob_end_clean();
        //return response()->file($path);
        //dd($path);
        return response('ok', 200)->withHeaders([
            'X-Sendfile' => $path,
            'Content-Type' => File::mimeType($path),
        ]);
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
//        $image = Image::getByHash($albumHash, $imageHash);
//        $imageExt = pathinfo($image->name, PATHINFO_EXTENSION);
//        $newName = $request->name;
//
//        $oldLocalPath = 'images'. $image->album->path . $image->name;
//        $newPath = $image->album->path ."$newName.$imageExt";
//        $newLocalPath = "images$newPath";
//        if (Storage::exists($newPath))
//            throw new ApiException(409, 'Album with this name already exist');
//
//        Storage::move($oldLocalPath, $newLocalPath);
//        $image->update([
//            'name' => basename($newPath),
//            'path' => "$newPath",
//        ]);
        return response(null, 204);
    }

    public function delete($albumHash, $imageHash)
    {
//        $image = Image::getByHash($albumHash, $imageHash);
//
//        $imagePath = 'images'. $image->album->path . $image->name;
//        Storage::delete($imagePath);
//
//        $thumbPath = "thumbs/*/$image->hash*";
//        File::delete(File::glob(Storage::path($thumbPath)));
//
//        $image->delete();

        return response(null, 204);
    }
}
