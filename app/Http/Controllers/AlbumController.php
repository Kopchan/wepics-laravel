<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\SortType;
use App\Exceptions\ApiException;
use App\Http\Requests\AlbumCreateRequest;
use App\Http\Requests\AlbumEditRequest;
use App\Http\Requests\AlbumRequest;
use App\Http\Resources\ImageResource;
use App\Models\Album;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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
        $targetAlbum = Album::getByHash($hash);
        if ($targetAlbum->getAccessLevelCached($user) == AccessLevel::None)
            throw new ApiException(403, 'Forbidden for you');

        AlbumController::indexingAlbumChildren($targetAlbum);

        ImageController::indexingImages($targetAlbum);

        return response(null);
    }
    public function get(AlbumRequest $request, $hash)
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
            'name'  =>                                "$naturalSort",
            'ratio' => "width / height $sortDirection, $naturalSort",
            default =>      "$sortType $sortDirection, $naturalSort",
        };
        $limit = intval($request->limit);
        if (!$limit)
            $limit = 4;

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
        ])->get();

        /*
        if ($user?->is_admin)
            $allowedChildren = $children;
        else
            $allowedChildren = $children->reject(fn ($child) => !$child->hasAccessCached($user));
        */
        $allowedChildren = collect();

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

        if ($limit)
            foreach ($allowedChildren as $child)
                $child['images'] = Image
                    ::where('album_id', $child->id)
                    ->limit($limit)
                    ->orderByRaw($orderByRaw)
                    ->get();

        // Проход по родителям альбома для ответа (цепочка родителей)
        $parentsChain = [];
        $ancestors = $targetAlbum->ancestors()->get();
        foreach ($ancestors as $ancestor) {
            if ($ancestor->getAccessLevelCached($user) === AccessLevel::None) break;
            $parentsChain[] = $ancestor;
        }

        // Компактный объект ответа
        $response = ['name' => $targetAlbum->name];
        if ($targetAlbum->last_indexation)
            $response['last_indexation'] = $targetAlbum->last_indexation;

        if (count($allowedChildren)) {
            foreach ($allowedChildren as $album) {
                $childData = [
                    'hash' => $album->hash,
                    'last_indexation' => $album->last_indexation,
                    'guest_allow' => $album->guest_allow,
                ];
                //if ($user && $limit && $album->getAccessLevelCached() != AccessLevel::AsGuest) $childData['sign'] = $album->getSign($user);
                if ($album->sign) $childData['sign'] = $album->sign;
                if ($album->albums_count) $childData['albums_count'] = $album->albums_count;
                if ($album->images_count) {
                    $childData['images_count'] = $album->images_count;
                    if ($limit) $childData['images'] = ImageResource::collection($album->images);
                }
                $childrenRefined[$album->name] = $childData;
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

    public function rename(AlbumEditRequest $request, $hash)
    {
        $album = Album::getByHash($hash);
        $oldCustomName = (basename($album->path) != $album->name)
            ? $album->name
            : null;

        $newFolderName = $request->name;
        if ($newFolderName !== null && $newFolderName !== '') {
            $oldLocalPath = "images$album->path";
            $newPath = dirname($album->path) .'/'. $newFolderName .'/';
            $newLocalPath = "images$newPath";
            if (Storage::exists($newPath))
                throw new ApiException(409, 'Album with this name already exist');

            Storage::move($oldLocalPath, $newLocalPath);
            $album->path = $newPath;
        }

        $album->name = $request->customName ?? $oldCustomName ?? $request->name ?? $album->name;

        $album->save();
        return response(null, 204);
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
