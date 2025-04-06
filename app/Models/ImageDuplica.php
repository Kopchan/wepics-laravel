<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageDuplica extends Model
{
    protected $table = 'image_duplica';

    // Заполняемые поля
    protected $fillable = [
        'image_id', 'name'
    ];

    // Связи
    public function image() {
        return $this->belongsTo(Image::class);
    }
}
