<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum SortAlbumType: string
{
    use EnumValues;

    case NAME    = 'name';
    case CONTENT = 'content'; // Присоединяет картинку и ищет по обычной сортировке картинок
    case CREATED = 'created';
    case INDEXED = 'indexed';
    case IMAGES = 'images';
    case ALBUMS = 'albums';
    case SIZE    = 'size';
}
