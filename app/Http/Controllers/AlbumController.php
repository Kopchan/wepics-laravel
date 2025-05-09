<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $contentSortTypeRaw = match ($contentSortType) {
            'reacts' => "reactions_count",
            'ratio'  =>     "width / height",
            'square' => "ABS(GREATEST(width, height) / LEAST(width, height) - 1)",
            default  => $contentSortType,
        };
        $contentSortDirection = $request->has('reverse') ? 'DESC' : 'ASC';
        $contentNaturalSort   = "natural_sort_key $contentSortDirection";
        $contentSort = match ($contentSortType) {
            'name'  =>                                             $contentNaturalSort,
            default => "$contentSortTypeRaw $contentSortDirection, $contentNaturalSort",
        };

        // Сортировка дочерних альбомов
        $albumsSortType      = $request->sortAlbums ?? SortAlbumType::values()[0];
        $albumsSortDirection = $request->has('reverse') ? 'DESC' : 'ASC';
        $albumOrderLevel     = $request->has('disrespect') ? '' : 'order_level DESC, ';
        $albumNaturalSort    = "natural_sort_key $albumsSortDirection";
        $albumSort = match ($albumsSortType) {
            'name'    =>                                           $albumNaturalSort,
            'content' => "content_sort_field $albumsSortDirection, $albumNaturalSort",
            'created' =>         "created_at $albumsSortDirection, $albumNaturalSort",
            'images'  =>       "images_count $albumsSortDirection, $albumNaturalSort",
            'albums'  =>       "albums_count $albumsSortDirection, $albumNaturalSort",
            'indexed' =>    "last_indexation $albumsSortDirection, $albumNaturalSort",
            default   =>    "$albumsSortType $albumsSortDirection, $albumNaturalSort",
        };
        $albumSort = $albumOrderLevel.$albumSort;

        // Кол-во загружаемых картинок ко всем альбомам
        if ($request->has('images'))
            $imagesLimitJoin = intval($request->images) ?? 0;
        else
            $imagesLimitJoin = 4;

        // Подзапрос для content_sort_field
        $contentSortFieldSubquery = Image
            ::whereColumn('album_id', 'albums.id')
            ->orderByRaw("content_sort_field $contentSortDirection")
            ->limit(1);

        if ($contentSortType === 'reacts') {
            $contentSortFieldSubquery
                //->withCount('reactions as content_sort_field')
                ->selectRaw(DB::raw(
"(
  select
    count(*)
  from
    `reactions`
    inner join `reaction_images` on `reactions`.`id` = `reaction_images`.`reaction_id`
  where
    `images`.`id` = `reaction_images`.`image_id`
) as `content_sort_field`"
                ));
        }
        else
            $contentSortFieldSubquery
                ->selectRaw("$contentSortTypeRaw as content_sort_field");

        // Нужно ли подгружать дочерние альбомы?
        $childrenIsRequired = !$request->has('simple');

        // Вычисление того что подгрузить к альбому
        $withCount = [
            'images',
            'childAlbums as albums_count',
        ];
        $withLoad = [
            'ancestors',
        ];
        if ($childrenIsRequired)
            $withLoad['childAlbums'] = fn($q) => $q
                ->withCount($withCount)
                ->withSum('images as size', 'size')
                ->addSelect($albumsSortType === 'content' ? [
                    'content_sort_field' => $contentSortFieldSubquery
                ] : [])
                ->orderByRaw($albumSort);

        if ($imagesLimitJoin) {
            $withLoad['images'] = fn($q) => $q
                ->withCount($contentSortType === 'reacts' ? 'reactions' : [])
                ->orderByRaw($contentSort)
                ->limit($imagesLimitJoin);
            // FIXME: медленнее, чем запрос картинок на каждом альбоме
            //$withLoad['childAlbums.images'] = fn($q) => $q->orderByRaw($contentSort)->limit($imagesLimitJoin);
        }

        // Получение альбома из БД и проверка доступа пользователю
        $targetAlbum = Album::getByHashOrAlias($hash, fn ($q) => $q
            ->withCount($withCount)
            ->withSum('images as size', 'size')
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
            $hasImages = $child->images_count > 0;
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

                // FIXME: быстрее, чем жадная загрузка
                $child['imagesLoaded'] = $query->get();
            }
        }

        // Проход по родителям альбома для ответа (цепочка родителей)
        foreach ($targetAlbum->ancestors as $index => $ancestor)
            if ($ancestor->getAccessLevelCached($user) === AccessLevel::None)
                $targetAlbum->ancestors->forget($index);

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
                throw new ApiException(409, 'Album with this name already exist');

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
        if ($request->has('urlName')) {
            $album->alias = $newAlias;

            if ($oldAlias) AlbumAlias::updateOrCreate(
                ['name' => $oldAlias],
                ['album_id' => $album->id],
            );
        }

        if ($request->has('ageRatingId' )) $album->age_rating_id = $request->ageRatingId;
        if ($request->has('orderLevel'  )) $album->order_level   = $request->orderLevel ?? 0;
        if ($request->has('viewSettings')) $album->view_settings = $request->viewSettings;
        if ($request->has('guestAllow'  )) $album->guest_allow   = $request->guestAllow;

        $album->save();
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
}
