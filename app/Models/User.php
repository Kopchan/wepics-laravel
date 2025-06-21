<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Cacheables\SpaceInfo;
use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    // Заполняемые поля
    protected $fillable = [
        'nickname',
        'password',
        'login',
        'quota',
    ];
    // Скрытие поля пароля
    protected $hidden = ['password'];

    // Преобразование полей
    protected $casts = [
        'password' => 'hashed', // Хеширование пароля
        'is_admin' => 'boolean', // Преобразование 1/0 из БД в true/false
    ];

    // Получение модель пользователя по токену
    static public function getByToken($token): User
    {
        $cacheKey = "user:token=$token";
        $user = Cache::get($cacheKey);
        if (!$user) {
            $tokenDB = Token::where('value', $token)->first();
            if (!$tokenDB)
                throw new ApiException(401, 'Invalid token');

            $user = $tokenDB->user;
            Cache::put($cacheKey, $user, 1800);
        }
        return $user;
    }

    // Генерация токена
    public function generateToken(): string
    {
        $token = Token::create([
            'user_id' => $this->id,
            'value' => Str::random(255),
        ]);
        return $token->value;
    }


    public function quotaUsed(): int
    {
        if (!$this->is_admin)
            return $this->images()->sum('size');
        else
            return DB::table('images', 'i')
                ->join((new Album)->getTable() .' as a', 'i.album_id', '=', 'a.id')
                ->whereNull('a.owner_user_id')
                ->sum('i.size');
    }

    public function quotaTotal(): int
    {
        if ($this->is_admin) {
            $space = SpaceInfo::get();
            $usedHim = $this->quotaUsed();
            return $space->total - ($space->used - $usedHim);
        }
        else {
            $userQuota = max(
                config('setups.default_quota_bytes'),
                $this->quota
            );
            $diskLimitedQuota = min(
                SpaceInfo::getCached()->free,
                $userQuota,
            );
            return $diskLimitedQuota;
        }
    }

    // Связи
    public function accessRights() {
        return $this->hasMany(AccessRight::class);
    }
    public function reactions() {
        return $this->hasMany(Reaction::class);
    }
    public function tags() {
        return $this->hasMany(Tag::class);
    }
    public function tokens() {
        return $this->hasMany(Token::class);
    }
    public function albumsViaAccess() {
        return $this->belongsToMany(Album::class, AccessRight::class)
            ->using(AccessRight::class);
    }
    public function images() {
        return $this->hasManyThrough(
            Image::class,
            Album::class,
            'owner_user_id',
            'album_id'
        );
    }
}
