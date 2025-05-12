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
use phpDocumentor\Reflection\Types\Static_;
use Staudenmeir\EloquentEagerLimit\HasEagerLimit;

class Album extends Model
{
    use NodeTrait, HasFactory;//, HasEagerLimit;

    // Поля для заполнения
    protected $fillable = [
        'name',
        'alias',
        'path',
        'hash',
        'last_indexation',
        'parent_album_id',
        'guest_allow',
        'age_rating_id',
        'order_level',
        'view_settings',
        'natural_sort_key',
    ];

    public function getParentIdName() {
        return 'parent_album_id';
    }

    protected $casts = [
        'guest_allow' => 'boolean',
    ];

    // Обработка событий модели
    protected static function booted()
    {
        static::saving(function ($item) {
            // Автоматически обновляем natural_sort_name при сохранении
            $item->natural_sort_key = self::normalizeName($item?->name ?? '');
        });
    }


    // Функции

    const MAX_SORT_KEY_LENGTH = 255;

    // Генерация имени для натуральной сортировки
    public static function normalizeName(string $originalName): string
    {
        // Нормализация чисел
        $normalizedName = preg_replace_callback('/\d+/', function ($matches) {
            return str_pad($matches[0], 12, '0', STR_PAD_LEFT);
        }, $originalName);

        $truncatedName = mb_substr($normalizedName, 0,self::MAX_SORT_KEY_LENGTH);

        // Собираем итоговое имя
        return iconv('UTF-8', 'UTF-8//IGNORE', $truncatedName);
    }

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
    /**
     * Получение альбома по его уникальному хешу или алиасам
     */
    static public function getByHashOrAlias($hashOrAlias, $modifyQuery = null): Album
    {
        $query = Album::query();
        if ($modifyQuery)
            $modifyQuery($query);

        if ($hashOrAlias !== 'root') {
            $album = $query
                ->where  ('hash' , $hashOrAlias)
                ->orWhere('alias', $hashOrAlias)
                ->first();

            if (!$album)
                throw new ApiException(404, "Album with \"$hashOrAlias\" not found");
        }
        else {
            $album = $query->firstOrCreate([
                'path' => '/'
            ], [
                'name' => '',
                'hash' => 'root',
            ]);
        }
        return $album;
    }

    // Проверка на доступ к статичным файлам альбома
    const SIGN_CACHE_TTL = 3600;
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

        Cache::put($cacheKey, $signCode, static::SIGN_CACHE_TTL);
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
        Cache::put($cacheKey, $signCode, static::SIGN_CACHE_TTL);

        return $allow;
    }

    // Проверки на доступ пользователя к альбому
    const ACCESS_CACHE_TTL = 604800;
    static public function buildAccessCacheKey(string $albumHash, int $userId = null): string {
        return "access:to=$albumHash;for=$userId";
    }
    public function getAccessLevelCached(User $user = null): AccessLevel
    {
        $cacheKey = static::buildAccessCacheKey($this->hash, $user?->id);
        $result = null;
        if ($this->guest_allow) {
            $result = AccessLevel::AsGuest;
            Cache::put($cacheKey, $result, static::ACCESS_CACHE_TTL);

            //if ($cacheKey !== 'access:to=n3sUrEBC67fWOrZQ61hgfBrvH;for=')
            //    dd($result, $cacheKey, Cache::get($cacheKey));
        }

        $result ??= Cache::get($cacheKey);

        if ($result === null) {
            $result = Album::getAccessLevelBatchById($this->id, $user?->id);
        }

        if ($result === AccessLevel::None && $user?->is_admin) {
            //dd($this->guest_allow, $result, $user?->is_admin);
            $result = AccessLevel::AsAdmin;
            Cache::put($cacheKey, $result, static::ACCESS_CACHE_TTL);
        }

        return $result;
    }
    public static function getAccessLevelBatchById(int $albumId, int $userId = null): AccessLevel
    {
        $result = AccessLevel::None;
        $ancestors = Album::reversed()->ancestorsAndSelf($albumId);

        if (count($ancestors) && $ancestors[0]->guest_allow)
            $result = AccessLevel::AsGuest;

        if (!$result && $userId !== null)
            $rights = AccessRight
                ::whereIn('album_id', $ancestors->pluck('id'))
                ->where('user_id', $userId)
                ->get();

        $passedAlbums = [];
        foreach ($ancestors as $ancestor) {
            $passedAlbums[] = $ancestor;
            if ($userId === null && $ancestor->guest_allow === false) {
                $result = AccessLevel::None;
                break;
            }

            if ($ancestor->guest_allow === true) {
                $result = AccessLevel::AsGuest;
                break;
            }

            if ($userId === null) continue;

            $right = isset($rights) ? $rights->where('album_id', $ancestor->id)->first() : null;
            if ($right === null) {
                if ($ancestor->guest_allow === false) {
                    $result = AccessLevel::None;
                    break;
                }
                continue;
            }

            $result = $right->allowed
                ? AccessLevel::AsAllowedUser
                : AccessLevel::None;
            break;
        }

        foreach ($passedAlbums as $passedAlbum) {
            Cache::put(static::buildAccessCacheKey($passedAlbum->hash, $userId), $result, static::ACCESS_CACHE_TTL);
        }

        return $result;
    }
    public static function getAccessLevelCachedByHash(string $albumHash, User $user = null): AccessLevel
    {

        $cacheKey = static::buildAccessCacheKey($albumHash, $user?->id);
        $result = Cache::get($cacheKey);

        if ($result === null) {
            $album = Album::getByHashOrAlias($albumHash);

            if ($album->guest_allow)
                $result = AccessLevel::AsGuest;

            if (!$result)
                $result = Album::getAccessLevelBatchById($album->id, $user?->id);
            else
                Cache::put(static::buildAccessCacheKey($albumHash, $user?->id), $result, static::ACCESS_CACHE_TTL);

        }

        if ($result === AccessLevel::None && $user?->is_admin) {
            $result = AccessLevel::AsAdmin;
            Cache::put(static::buildAccessCacheKey($albumHash, $user?->id), $result, static::ACCESS_CACHE_TTL);
        }

        return $result;
    }

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
