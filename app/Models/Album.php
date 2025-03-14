<?php

namespace App\Models;

use App\Enums\AccessLevel;
use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Kalnoy\Nestedset\NodeTrait;

class Album extends Model
{
    use NodeTrait, HasFactory;

    // Поля для заполнения
    protected $fillable = [
        'name',
        'path',
        'hash',
        'last_indexation',
        'parent_album_id',
        'guest_allow',
    ];

    public function getParentIdName() {
        return 'parent_album_id';
    }

    protected $casts = [
        'guest_allow' => 'boolean',
    ];

    // Функции
    /**
     * Получение альбома по его уникальному хешу
     */
    static public function getByHash($hash): Album
    {
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

    // Проверка на доступ к статичным файлам альбома
    static public function buildSignCacheKey(string $albumHash, int $userId = null): string {
        return "signAccess:to=$albumHash;for=$userId";
    }
    public function getSign(User $user) {
        return static::getSignStatic($this->hash, $user);
    }
    public static function getSignStatic($albumHash, User $user): string
    {
        $cacheKey = static::buildSignCacheKey($albumHash, $user->id);
        $cachedSign = Cache::get($cacheKey);
        if ($cachedSign) return $user->id .'_'. $cachedSign;

        $currentDay = date("Y-m-d");
        $userToken = $user->tokens[0]->value;

        $string = $userToken . $currentDay . $albumHash;
        $signCode = base64_encode(Hash::make($string));

        Cache::put($cacheKey, $signCode, 3600);
        return $user->id .'_'. $signCode;
    }
    public function checkSign($sign) {
        return static::checkSignStatic($this->hash, $sign);
    }
    public static function checkSignStatic($albumHash, $sign): bool
    {
        try {
            $signExploded = explode('_', $sign);
            $userId   = $signExploded[0];
            $signCode = $signExploded[1];
        }
        catch (\Exception $e) {
            return false;
        }

        $cacheKey = static::buildSignCacheKey($albumHash, $userId);
        $cachedSign = Cache::get($cacheKey);
        if ($cachedSign === $signCode) return true;

        $user = User::find($signExploded[0]);
        if (!$user)
            return false;

        $currentDay = date("Y-m-d");
        $string = $user->tokens[0]->value . $currentDay . $albumHash;

        $allow = Hash::check($string, base64_decode($signExploded[1]));
        Cache::put($cacheKey, $signCode, 3600);

        return $allow;
    }

    // Проверки на доступ пользователя к альбому
    static public function buildAccessCacheKey(string $albumHash, int $userId = null): string {
        return "access:to=$albumHash;for=$userId";
    }
    public function getAccessLevelCached(User $user = null): AccessLevel
    {
        if ($user?->is_admin) return AccessLevel::AsAdmin;

        $cacheKey = static::buildAccessCacheKey($this->hash, $user?->id);
        $allowLevel = Cache::get($cacheKey);

        if ($allowLevel === null) {
            $allowLevel = Album::getAccessLevelBatchById($this->id, $user?->id);
        }

        return $allowLevel;
    }
    public static function getAccessLevelBatchById(int $albumId, int $userId = null): AccessLevel
    {
        $ancestors = Album::ancestorsAndSelf($albumId);

        if ($userId !== null)
            $rights = AccessRight
                ::whereIn('album_id', $ancestors->pluck('id'))
                ->where('user_id', $userId)
                ->get();

        $passedAlbums = [];
        $result = AccessLevel::None;
        foreach ($ancestors as $ancestor) {
            $passedAlbums[] = $ancestor;
            if ($userId === null && $ancestor->guest_allow === false) {
                $result = AccessLevel::None;
                break;
            }

            if ($ancestor->guest_allow === true) {
                $result = AccessLevel::AsGuest;
            }

            if ($userId === null) {
                continue;
            }

            $right = $rights->where(['album_id'])->first();
            if ($right === null) {
                continue;
            }

            $result = $right->allowed
                ? AccessLevel::AsAllowedUser
                : AccessLevel::None;
        }
        foreach ($passedAlbums as $passedAlbum) {
            Cache::put(static::buildAccessCacheKey($passedAlbum->hash, $userId), $result, 864000);
        }

        return $result;
    }
    public static function getAccessLevelCachedByHash(string $albumHash, User $user = null): AccessLevel
    {
        if ($user?->is_admin) return AccessLevel::AsAdmin;
        Album::query();

        $cacheKey = static::buildAccessCacheKey($albumHash, $user?->id);
        $allowLevel = Cache::get($cacheKey);

        if ($allowLevel === null) {
            $album = Album::getByHash($albumHash);
            $allowLevel = Album::getAccessLevelBatchById($album->id, $user?->id);
        }
        return $allowLevel;
    }
    /*
    public static function getAccessLevelBatchByHash(string $albumHash, int $userId = null): AccessLevel
    {
        $ancestors = Album::where('hash', $albumHash)::ancestorsAndSelf($albumHash);

        if ($userId !== null)
            $rights = AccessRight
                ::whereIn('album_id', $ancestors->pluck('id'))
                ->where('user_id', $userId)
                ->get();

        $passedAlbums = [];
        $result = AccessLevel::None;
        foreach ($ancestors as $ancestor) {
            $passedAlbums[] = $ancestor;
            if ($userId === null && $ancestor->guest_allow === false) {
                $result = AccessLevel::None;
                break;
            }

            if ($ancestor->guest_allow === true) {
                $result = AccessLevel::AsGuest;
            }

            if ($userId === null) {
                continue;
            }

            $right = $rights->where(['album_id'])->first();
            if ($right === null) {
                continue;
            }

            $result = $right->allowed
                ? AccessLevel::AsAllowedUser
                : AccessLevel::None;
        }
        foreach ($passedAlbums as $passedAlbum) {
            Cache::put(static::buildAccessCacheKey($passedAlbum->hash, $userId), $result, 864000);
        }

        return $result;
    }
    */



    /*
    public static function hasAccessCachedByHash(string $hash, User $user = null): AccessLevel
    {
        if ($user?->is_admin) return AccessLevel::AsAdmin;

        $cacheKey = static::buildAccessCacheKey($hash, $user?->id);
        $allowLevel = Cache::get($cacheKey);

        if ($allowLevel === null) {
            $allowLevel = Album::hasAccessFastByHash($hash, $user?->id);
            Cache::put($cacheKey, $allowLevel, 864000);
        }
        return $allowLevel;
    }
    public static function hasAccessFastById(int $albumId, int $userId = null): bool
    {
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
    public static function hasAccessFastByHash(string $hash, int $userId = null): bool
    {
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

    public function getAllDescendants()
    {
        $descendants = collect();

        foreach ($this->childAlbums as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }
}
