<?php

namespace App\Models;

use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    // Поля
    protected $fillable = [
        'link',
        'expires_at',
        'album_id',
        'join_limit',
    ];

    // Функции
    public function getRouteKeyName() {
        return 'link';
    }

    public function failOnExpires(): void
    {
        if ((
            $this->expires_at === null ||
            !now()->greaterThan($this->expires_at)
        ) && (
            $this->join_limit === null ||
            $this->join_limit > 0
        )) return;

        $this->delete();
        throw new ApiException(409, 'Invitation expired');
    }

    // Связи
    public function album() {
        return $this->belongsTo(Album::class);
    }
}
