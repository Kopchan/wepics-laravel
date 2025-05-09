<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgeRating extends Model
{
    // Заполняемое поле
    protected $fillable = [
        'code',
        'name',
        'description',
        'color',
        'level',
        'preset',
    ];
}
