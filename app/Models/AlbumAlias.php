<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlbumAlias extends Model
{
    use HasFactory;

    protected $table = 'legacy_aliases';

    protected $fillable = [
        'name',
        'album_id',
    ];
}
