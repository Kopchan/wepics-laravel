<?php

namespace App\Models;

use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Album extends Model
{
    use HasFactory;

    // Поля для заполнения
    protected $fillable = [
        'name',
        'path',
        'hash',
        'last_indexation',
        'parent_album_id',
    ];

    // Функции
    /**
     * Получение альбома по его уникальному хешу
     */
    static public function getByHash($hash): Album {
        if ($hash != 'root') {
            $album = Album::where('hash', $hash)->first();
            if (!$album)
                throw new ApiException(404, "Album with hash \"$hash\" not found");
        }
        else {
            $album = Album::where('path', '/')->first();
            if (!$album)
                $album =  Album::create([
                    'name' => '',
                    'path' => '/',
                    'hash' => 'root',
                ]);
        }
        return $album;
    }
    /**
     * Проверка пользователя на доступ к альбому
     */

    public function hasAccessCached(User $user = null) {
        if ($user?->is_admin) return true;

        $cacheKey = "access:to=$this->hash;for=$user?->id";
        $allow = Cache::get($cacheKey);

        if ($allow === null) {
            //$allow = $this->hasAccess($user);
            $allow = Album::hasAccessFastById($this->id, $user?->id);
            Cache::put($cacheKey, $allow, 86400);
        }
        return $allow;
    }

    public static function hasAccessCachedByHash(string $hash, User $user = null) {
        if ($user?->is_admin) return true;

        $cacheKey = "access:to=$hash;for=$user?->id";
        $allow = Cache::get($cacheKey);

        if ($allow === null) {
            $album = Album::getByHash($hash);
            //$allow = $album->hasAccess($user);
            $allow = Album::hasAccessFastByHash($hash, $user?->id);
            Cache::put($cacheKey, $allow, 86400);
        }
        return $allow;
    }

    public static function hasAccessFastById(int $albumId, int $userId = null): bool {
        $right = AccessRight
            ::where('album_id', $albumId)
            ->where('user_id', $userId)
            ->first();

        if (!$right && $userId)
            $right = AccessRight
                ::where('album_id', $albumId)
                ->where('user_id', null)
                ->first();

        if ($right)
            return $right->allowed;

        $parentAlbumId = Album::find($albumId)->parent_album_id;
        if ($parentAlbumId)
            return Album::hasAccessFastById($parentAlbumId, $userId);

        return false;
    }
    public static function hasAccessFastByHash(string $hash, int $userId = null): bool {
        $album = Album::getByHash($hash);
        $right = AccessRight
            ::where('album_id', $album->id)
            ->where('user_id', $userId)
            ->first();

        if (!$right && $userId)
            $right = AccessRight
                ::where('album_id', $album->id)
                ->where('user_id', null)
                ->first();

        if ($right)
            return $right->allowed;

        $parentAlbumId = $album->parent_album_id;
        if ($parentAlbumId)
            return Album::hasAccessFastById($parentAlbumId, $userId);

        return false;
    }
    /*
    public function hasAccess(User $user = null): bool {
        // Проверка админ ли пользователь
        //if ($user?->is_admin)
        //    return true;

        // Проверка есть ли доступ гостю
        $right = AccessRight
            ::where('user_id' , null)
            ->where('album_id', $this->id)
            ->first();
        if ($right?->allowed)
            return true;

        if ($user) {
            // Проверка есть ли доступ пользователю
            $right = AccessRight
                ::where('user_id' , $user->id)
                ->where('album_id', $this->id)
                ->first();
            if ($right?->allowed)
                return true;
        }

        return false;
    }
    public static function hasAccessFastOldByHash(string $hash, int $userId = null) {
        $res = DB
            ::table('access_rights')
            ->rightJoin('albums', 'access_rights.album_id', '=', 'albums.id')
            ->where('user_id', $userId)
            ->where('hash', $hash)
            ->select('allowed', 'parent_album_id', 'path')
            ->first();

        if ($res !== null) {
            if ($res->allowed !== null) return $res->allowed;
            if ($res->path === '/')     return false;
        }
        return Album::hasAccessFastOldByHash($res->parent_album_id, $userId);
    }

    public static function hasAccessFastOldById(int $albumId, int $userId = null): bool {
        $res = DB
            ::table('access_rights')
            ->rightJoin('albums', 'access_rights.album_id', '=', 'albums.id')
            ->where('user_id', $userId)
            ->where('album_id', $albumId)
            ->select('allowed', 'parent_album_id', 'path')
            ->first();
        // TODO: сделать несколько селектов в одном запросе, а то where обнуляют все поиски альбома и path уже не чекнуть
        throw new ApiDebugException([
            'res' => $res,
            'user_id' => $userId,
            'album_id' => $albumId,
        ]);
        if ($res !== null) {
            if ($res->allowed !== null) return $res->allowed;
            if ($res->path === '/')     return false;
        }
        return Album::hasAccessFastOldById($res->parent_album_id, $userId);
    }
    */
    // Связи
    public function images() {
        return $this->hasMany(Image::class);
    }
    public function accessRights() {
        return $this->hasMany(AccessRight::class);
    }
    public function parentAlbum() {
        return $this->belongsTo(Album::class, 'parent_album_id');
    }
    public function childAlbums() {
        return $this->hasMany(Album::class, 'parent_album_id');
    }
}
