<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\MediaType;
use App\Enums\SortAlbumType;
use App\Enums\SortType;
use App\Exceptions\ApiException;
use App\Http\Requests\AlbumCreateRequest;
use App\Http\Requests\AlbumUpdateRequest;
use App\Http\Requests\AlbumRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\ImageResource;
use App\Models\Album;
use App\Models\AlbumAlias;
use App\Models\Image;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class AlbumController extends Controller
{
    public static function indexingAlbumChildren(Album $album): array
    {
        // TODO: Перейти на свою индексацию через glob для быстрой и одновременной индексации картинок и папок (мб всех файлов)
        // Получение пути к альбому и его папок
        $localPath = Storage::path("images$album->path");
      //$folders = array_filter(glob("$localPath*", GLOB_MARK), fn ($path) => in_array($path[-1], ['/', '\\']));
      //$folders = Storage::directories($localPath);
        $folders = File::directories($localPath);
        $childrenInDB = $album->childAlbums->toArray();

        // Проход по папкам альбома для ответа (дочерние альбомы)
        $children = [];
        foreach ($folders as $folder) {
            $path = $album->path . basename($folder) .'/';

            // Проверка наличия в БД вложенного альбома, создание если нет
            $key = array_search($path, array_column($childrenInDB, 'path'));
            if ($key !== false) {
                $childAlbum = $childrenInDB[$key];
                unset($childrenInDB[$key]);
                $childrenInDB = array_values($childrenInDB);
            }
            else {
                $childAlbum = Album::create([
                    'name' => basename($path),
                    'path' => $path,
                    'hash' => Str::random(25),
                    'parent_album_id' => $album->id
                ]);
            }
            $children[] = $childAlbum;
        }
        // Удаление оставшихся альбомов в БД
        //Album::destroy(array_column($childrenInDB, 'id')); // FIXME: опасное удаление, надо спрашивать админа "куда делся тот-та альбом?"

        $album->last_indexation = now();
        $album->save();
        return $children;
    }
    public function reindex($hash)
    {
        // Получение пользователя
        $user = request()->user();

        // Получение альбома из БД и проверка доступа пользователю
        $targetAlbum = Album::getByHashOrAlias($hash);
        if ($targetAlbum->getAccessLevelCached($user) == AccessLevel::None)
            throw new ApiException(403, 'Forbidden for you');

        AlbumController::indexingAlbumChildren($targetAlbum);

        ImageController::indexingImages($targetAlbum);

        return response(null);
    }
    public function getLegacy(AlbumRequest $request, $hash)
    {
        // Получение пользователя
        $user = request()->user();

        // Фильтры
        $allowedSorts = array_column(SortType::cases(), 'value');
        $sortType = $request->sort ?? $allowedSorts[0];

        $sortDirection = $request->has('reverse') ? 'DESC' : 'ASC';
        //$naturalSort = "udf_NaturalSortFormat(name, 10, '.') $sortDirection";
        $naturalSort = "natural_sort_key $sortDirection";
        $orderByRaw = match ($sortType) {
            'name'       =>                                          $naturalSort,
            'reacts'     =>    "reactions_count" . " $sortDirection, $naturalSort",
            'ratio'      =>     "width / height" . " $sortDirection, $naturalSort",
            'squareness' => "ABS(width / height - 1) $sortDirection, $naturalSort",
            default      =>               "$sortType $sortDirection, $naturalSort",
        };
        $albumImagesJoin = intval($request->images);
        if (!$albumImagesJoin)
            $albumImagesJoin = 4;

        // Получение альбома из БД и проверка доступа пользователю
        $targetAlbum = Album::getByHash($hash);
        if ($targetAlbum->getAccessLevelCached($user) == AccessLevel::None)
            throw new ApiException(403, 'Forbidden for you');

        // Получение вложенных альбомов из БД если индексировалось, иначе индексировать
        //TODO: мб добавить опцию через сколько времени надо переиндексировать?
        if ($targetAlbum->last_indexation === null)
            AlbumController::indexingAlbumChildren($targetAlbum);

        $children = Album::where('parent_album_id', $targetAlbum->id)->withCount([
            'images',
            'childAlbums as albums_count'
        ])->orderBy('order_level')->get();

        /*
        if ($user?->is_admin)
            $allowedChildren = $children;
        else
            $allowedChildren = $children->reject(fn ($child) => !$child->hasAccessCached($user));
        */
        $allowedChildren = collect();

        //$keys = [];
        //foreach ($children as $child) {
        //    $keys[] = Album::buildAccessCacheKey($child->hash, $user?->id);
        //}
        //$values = Redis::mget($keys);
        //dd($values, Cache::get(Album::buildSignCacheKey($children[0]->hash, $user?->id)),$keys);

        foreach ($children as $child) {
            $level = $child->getAccessLevelCached($user);
            //dd($child, $level);
            switch ($level) {
                case AccessLevel::None:
                    break;

                case AccessLevel::AsAllowedUser;
                case AccessLevel::AsAdmin:
                    $child['sign'] = $child->getSign($user);
                case AccessLevel::AsGuest:
                    $allowedChildren->push($child);
                    break;
            }
        }

        if ($albumImagesJoin)
            foreach ($allowedChildren as $child) {
                // TODO: eagle loading, please!
                $query = Image
                    ::where('album_id', $child->id)
                    ->limit($albumImagesJoin)
                    ->orderByRaw($orderByRaw);

                if ($sortType === 'reacts')
                    $query->withCount('reactions');

                $child['images'] = $query->get();
            }

        // Проход по родителям альбома для ответа (цепочка родителей)
        $parentsChain = [];
        $ancestors = $targetAlbum->ancestors()->get();
        foreach ($ancestors as $ancestor) {
            if ($ancestor->getAccessLevelCached($user) === AccessLevel::None) break;
            $parentsChain[] = $ancestor;
        }

        // Компактный объект ответа
        $response = ['name' => $targetAlbum->name];
        if ($targetAlbum->order_level    ) $response['order_level'    ] = $targetAlbum->order_level;
        if ($targetAlbum->albums_count   ) $response['albums_count'   ] = $targetAlbum->albums_count;
        if ($targetAlbum->last_indexation) $response['last_indexation'] = $targetAlbum->last_indexation;
        if ($targetAlbum->age_rating_id  ) $response['ratingId'] = $targetAlbum->age_rating_id;

        if (count($allowedChildren)) {
            foreach ($allowedChildren as $album) {
                $childData = [
                    'hash' => $album->hash,
                    'guest_allow' => $album->guest_allow,
                ];
                if ($album->sign) $childData['sign'] = $album->sign;
                if ($album->age_rating_id  ) $childData['ratingId'       ] = $album->age_rating_id;
                if ($album->order_level    ) $childData['order_level'    ] = $album->order_level;
                if ($album->albums_count   ) $childData['albums_count'   ] = $album->albums_count;
                if ($album->last_indexation) $childData['last_indexation'] = $album->last_indexation;
                if ($album->images_count) {
                    $childData['images_count'] = $album->images_count;
                    if ($albumImagesJoin) $childData['images'] = ImageResource::collection($album->images);
                }
                $childrenRefined[$album->name] = $childData; // $album->name убивает с одним и тем же именем объекты
            }
            $response['children'] = $childrenRefined;
        }
        if (count($parentsChain)) {
            foreach ($parentsChain as $album) {
                if ($album->path === '/') $parentsChainRefined['/'] = ['hash' => $album->hash];
                else             $parentsChainRefined[$album->name] = ['hash' => $album->hash];
            }
            $response['parentsChain'] = $parentsChainRefined;
        }
        return response($response);
    }

    public function get(AlbumRequest $request, $hash)
    {
        // Получение пользователя
        $user = $request->user();

        // Сортировка контента
        $contentSortType = $request->sort ?? SortType::values()[0];

        $seed = $request->seed ?? (
            $contentSortType === 'random'
            ? mt_rand(100_000, 999_999)
            : null
        );

        $contentSortTypeRaw = match ($contentSortType) {
            'random'     => 'RAND('.DB::getPdo()->quote($seed).')',
            'reacts'     => "reactions_count",
            'ratio'      => "width / height",
            'square'     => "ABS(GREATEST(width, height) / LEAST(width, height) - 1)",
            'frames'     => "frames_count IS NULL, frames_count",
            'duration'   => "duration_ms IS NULL, duration_ms",
            'framerate'  => "avg_frame_rate_den IS NULL, avg_frame_rate_num / avg_frame_rate_den",
            'bitrate'    => "duration_ms IS NULL, size * 8 / duration_ms * 1000",
            default      => $contentSortType,
        };
        $contentSortTypeRawAdd = match ($contentSortType) {
            'duration',
            'bitrate'    => 'duration_ms'       ." IS NULL, $contentSortTypeRaw",
            'frames'     => 'frames_count'      ." IS NULL, $contentSortTypeRaw",
            'framerate'  => 'avg_frame_rate_den'." IS NULL, $contentSortTypeRaw",
            default      =>                                 $contentSortTypeRaw,
        };
        $contentSortDirection = $request->has('reverse') ? 'DESC' : 'ASC';
        $contentNaturalSort   = "natural_sort_key $contentSortDirection";
        $contentSort = match ($contentSortType) {
            'name'  =>                                             $contentNaturalSort,
            default => "$contentSortTypeRawAdd $contentSortDirection, $contentNaturalSort",
        };

        // Сортировка дочерних альбомов
        $albumsSortType = $request->sortAlbums ?? SortAlbumType::values()[0];

        $seed = $seed ?? (
        $albumsSortType === 'random'
            ? mt_rand(100_000, 999_999)
            : null
        );

        $albumsSortTypeRaw = match ($albumsSortType) {
            'random'   => 'RAND('.DB::getPdo()->quote($seed).')',
            'content'  => "content_sort_field",
            'medias'   => "medias_count",
            'images'   => "images_count",
            'videos'   => "videos_count",
            'audios'   => "audios_count",
            'albums'   => "albums_count",
            'indexed'  => "last_indexation",
            'created'  => "created_at",
            default    => $albumsSortType,
        };
        $albumsSortDirection = $request->has('reverseAlbums') ? 'DESC' : 'ASC';
        $albumNaturalSort    = "natural_sort_key $albumsSortDirection";
        $albumSort = match ($albumsSortType) {
            'name'  =>                                           $albumNaturalSort,
            default => "$albumsSortTypeRaw $albumsSortDirection, $albumNaturalSort",
        };
        $albumOrderLevel = $request->has('disrespect') ? '' : 'order_level DESC, ';
        $albumSort = $albumOrderLevel.$albumSort;

        // Кол-во загружаемых картинок ко всем альбомам
        if ($request->has('images'))
            $imagesLimitJoin = intval($request->images) ?? 0;
        else
            $imagesLimitJoin = 4;

        $mediaTypes = [];
        if ($request->types) {
            foreach ($request->types as $type) {
                $mediaTypes[] = match ($type) {
                    MediaType::Image->value => 'image',
                    MediaType::Video->value => 'video',
                    MediaType::Audio->value => 'audio',
                    MediaType::ImageAnimated->value => 'imageAnimated',
                };
            }
        }

        // Подзапрос для content_sort_field
        $contentSortFieldSubquery = Image
            ::whereColumn('album_id', 'albums.id')
            ->orderByRaw("content_sort_field $contentSortDirection")
            ->limit(1);

        if ($contentSortType === 'reacts') {
            $contentSortFieldSubquery
                //->withCount('reactions as content_sort_field')
                ->selectRaw(DB::raw('('
                .  'select count(*)'
                .  'from `reactions`'
                .  'inner join `reaction_images` on `reactions`.`id` = `reaction_images`.`reaction_id`'
                .  'where `images`.`id` = `reaction_images`.`image_id`'
                .') as `content_sort_field`'));
        }
        else {
            $contentSortFieldSubquery
                ->selectRaw("$contentSortTypeRaw as content_sort_field");
        }

        // Нужно ли подгружать дочерние альбомы?
        $childrenIsRequired = !$request->has('simple');

        // Вычисление того что подгрузить к альбому
        $withCount = [
            'images as medias_count',
            'images as images_count' => fn($q) => $q->where  ('type',  'image'),
            'images as videos_count' => fn($q) => $q->whereIn('type', ['video', 'imageAnimated']),
            'images as audios_count' => fn($q) => $q->where  ('type',  'audio'),
            'childAlbums as albums_count',
        ];
        $withLoad = [
            'ancestors',
        ];
        if ($childrenIsRequired)
            $withLoad['childAlbums'] = fn($q) => $q
                ->withCount($withCount)
                ->withSum('images as size'    , 'size')
                ->withSum('images as duration', 'duration_ms')
                ->addSelect($albumsSortType === 'content' ? [
                    'content_sort_field' => $contentSortFieldSubquery
                ] : [])
                ->orderByRaw($albumSort);

        if ($imagesLimitJoin) {
            $withLoad['images'] = function ($query) use ($contentSortType, $contentSort, $imagesLimitJoin, $mediaTypes) {
                $query
                    ->orderByRaw($contentSort)
                    ->limit($imagesLimitJoin);

                if ($contentSortType === 'reacts')
                    $query->withCount('reactions');

                if (count($mediaTypes))
                    $query->whereIn('type', $mediaTypes);

                return $query;
            };

            // FIXME: медленнее, чем запрос картинок на каждом альбоме
            //$withLoad['childAlbums.images'] = fn($q) => $q->orderByRaw($contentSort)->limit($imagesLimitJoin);
        }

        // Получение альбома из БД и проверка доступа пользователю
        $targetAlbum = Album::getByHashOrAlias($hash, fn ($q) => $q
            ->withCount($withCount)
            ->withSum('images as size'    , 'size')
            ->withSum('images as duration', 'duration_ms')
            ->with($withLoad)
        );

        if ($targetAlbum->getAccessLevelCached($user) == AccessLevel::None)
            throw new ApiException(403, 'Forbidden for you');

        // Получение вложенных альбомов из БД если индексировалось, иначе индексировать
        // TODO: мб добавить опцию через сколько времени надо переиндексировать?
        if ($targetAlbum->last_indexation === null)
            AlbumController::indexingAlbumChildren($targetAlbum);

        // Проход по дочерним альбомам и запись сигнатур-токенов для получения картинок
        foreach ($targetAlbum->childAlbums as $index => $child) {
            $level = $child->getAccessLevelCached($user);
            $hasImages = $child->medias_count > 0;
            switch ($level) {
                case AccessLevel::None:
                    $targetAlbum->childAlbums->forget($index);
                    continue 2;

                case AccessLevel::AsAllowedUser;
                case AccessLevel::AsAdmin:
                    if ($hasImages)
                        $child['sign'] = $child->getSign($user);
            }
            if ($hasImages && $imagesLimitJoin) {
                $query = Image
                    ::where('album_id', $child->id)
                    ->limit($imagesLimitJoin)
                    ->orderByRaw($contentSort);

                if ($contentSortType === 'reacts')
                    $query->withCount('reactions');

                if (count($mediaTypes))
                    $query->whereIn('type', $mediaTypes);

                // FIXME: быстрее, чем жадная загрузка
                $child['imagesLoaded'] = $query->get();
            }
        }

        // Проход по родителям альбома для ответа (цепочка родителей)
        //foreach ($targetAlbum->ancestors->reverse() as $index => $ancestor)
        //    if ($ancestor->getAccessLevelCached($user) === AccessLevel::None) {
        //        $targetAlbum->ancestors->forget($index);
        //        dd($ancestor);
        //        break;
        //    }

        $ancestors = $targetAlbum->ancestors;
        $cutIndex = null;

        foreach ($ancestors as $index => $ancestor) {
            if ($ancestor->getAccessLevelCached($user) === AccessLevel::None) {
                $cutIndex = $index;
                break;
            }
        }

        if (!is_null($cutIndex)) {
            // Обрезаем предков, начиная со следующего после первого "None"
            $targetAlbum->ancestors = $ancestors->slice($cutIndex + 1);
        }

        if ($seed)
            $targetAlbum->seed = $seed;

        return response(AlbumResource::make($targetAlbum));
    }

    public function create(AlbumCreateRequest $request, $hash)
    {
        $parentAlbum = Album::getByHash($hash);
        $newFolderName = $request->name;

        $path = "images$parentAlbum->path$newFolderName";
        if (Storage::exists($path))
            throw new ApiException(409, 'Album with this name already exist');

        $name = $request->customName ?? basename($path);

        Storage::createDirectory($path);
        $newAlbum = Album::create([
            'name' => $name,
            'path' => $path,
            'hash' => Str::random(25),
            'parent_album_id' => $parentAlbum->id
        ]);
        return response($newAlbum);
    }

    public function update(AlbumUpdateRequest $request, $hash)
    {
        $album = Album::getByHash($hash);

        // Внутреннее имя (папки)
        $newFolderName = $request->pathName;
        if (!empty($newFolderName)) {
            $oldLocalPath = "images$album->path";
            $newPath = dirname($album->path) .'/'. $newFolderName .'/';
            $newLocalPath = "images$newPath";
            if (Storage::exists($newPath))
                throw new ApiException(409, 'Album with this internal name already exist');

            Storage::move($oldLocalPath, $newLocalPath);
            $album->path = $newPath;
        }

        // Отображаемое имя
        $oldDisplayName = (basename($album->path) != $album->name)
            ? $album->name
            : null;

        $album->name = $request->displayName ?? $oldDisplayName ?? $request->name ?? $album->name;

        // Имя в ссылке (алиас)
        $oldAlias = $album->alias;
        $newAlias = $request->urlName;
        if ($request->has('urlName') && $newAlias != $oldAlias) {
            $album->alias = $newAlias;

            if ($oldAlias) AlbumAlias::updateOrCreate(
                ['name' => $oldAlias],
                ['album_id' => $album->id],
            );
        }
        $guestAllowBefore = $album->guest_allow;

        if ($request->has('ageRatingId' )) $album->age_rating_id = $request->ageRatingId;
        if ($request->has('orderLevel'  )) $album->order_level   = $request->orderLevel ?? 0;
        if ($request->has('viewSettings')) $album->view_settings = $request->viewSettings;
        if ($request->has('guestAllow'  )) $album->guest_allow   = $request->guestAllow;

        $album->save();

        if ($guestAllowBefore !== $album->guest_allow)
            Album::getAccessLevelBatchById($album->id);

        return response(AlbumResource::make($album), 200);
    }

    public function delete($hash)
    {
        $album = Album::getByHash($hash);
        $path = Storage::path("images$album->path");

        if ($album->path == '/')
            File::cleanDirectory($path);
        else
            File::deleteDirectory($path);

        $album->delete();
        return response(null, 204);
    }

    public function ogView($hashOrAlias)
    {
        $album = Album::getByHashOrAlias($hashOrAlias, fn ($q) => $q
            ->withSum('images as size', 'size')
            ->withSum('images as duration', 'duration_ms')
            ->withCount([
                'images as medias_count',
                'images as images_count' => fn($q) => $q->where  ('type',  'image'),
                'images as videos_count' => fn($q) => $q->whereIn('type', ['video', 'imageAnimated']),
                'images as audios_count' => fn($q) => $q->where  ('type',  'audio'),
                'childAlbums as albums_count',
            ])
            ->with([
                'images' => fn ($q) => $q->limit(20)->orderByDesc('date'),
            ])
        );
        if ($album->getAccessLevelCached() === AccessLevel::None)
            throw new ApiException(403, 'Access denied');

        $total = 0;
        $count = count($album->images);
        foreach ($album->images as $image)
            $total += $image->ratio = $image->width / $image->height;

        $album->avgRatio = $count > 0 ? $total / $count : 0;

        return view('album', compact('album'));
    }

    public function ogImage($hashOrAlias) {
        $path = storage_path("app/og/{$hashOrAlias}.png");

        // Если файл уже существует и не устарел — возвращаем его
        if (file_exists($path) && now()->diffInMinutes(Carbon::createFromTimestamp(filemtime($path))) < 60) {
            return response()->file($path, ['Content-Type' => 'image/png']);
        }

        // Генерация HTML
        $html = $this->ogView($hashOrAlias)->render();

        // Убедитесь, что директория существует
        if (!file_exists(dirname($path)))
            mkdir(dirname($path), 0755, true);

        // Генерация и сохранение скриншота
        Browsershot::html($html)
            ->windowSize(1200, 1200)
            ->save($path);

        return response()->file($path, ['Content-Type' => 'image/png']);
    }
}
