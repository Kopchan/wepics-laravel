<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ReactionImage extends Pivot
{
    protected $table = 'reaction_images';

    // Заполняемые поля
    protected $fillable = [
        'image_id', 'reaction_id', 'user_id'
    ];

    // Связи
    public function image() {
        return $this->belongsTo(Image::class);
    }
    public function reaction() {
        return $this->belongsTo(Reaction::class);
    }
    public function user() {
        return $this->belongsTo(User::class);
    }
}
