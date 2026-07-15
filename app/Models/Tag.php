<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    // Заполняемые поля
    protected $fillable = ['name'];

    // Поиск по значению
    static public function findFromString($string) {
        return Tag::where('name', $string)->first();
    }

    // Связи
    public function images() {
        return $this->belongsToMany(Image::class, 'tag_image');
    }
}
