<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum SortAlbumType: string
{
    use EnumValues;

    case Name        = 'name';
    case Content     = 'content'; // Присоединяет картинку и ищет по обычной сортировке картинок
    case CreatedAt   = 'created';
    case IndexedAt   = 'indexed';
    case Size        = 'size';
    case AlbumsCount = 'albums';
    case MediasCount = 'medias';
    case ImagesCount = 'images';
    case VideosCount = 'videos';
    case AudiosCount = 'audios';
}
