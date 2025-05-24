<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum SortAlbumType: string
{
    use EnumValues;

    case Name        = 'name';      // По натуральной сортировке названия
    case Content     = 'content';   // Присоединяет картинку и ищет по обычной сортировке картинок
    case Duration    = 'duration';  // Длительность всех вложенных видео
    case Size        = 'size';      // Вес всех вложенных медиа
    case AlbumsCount = 'albums';    // Вес всех вложенных медиа
    case MediasCount = 'medias';    // Кол-во любого медиа
    case ImagesCount = 'images';    // Кол-во картинок
    case VideosCount = 'videos';    // Кол-во видео
    case AudiosCount = 'audios';    // Кол-во аудио
    case CreatedAt   = 'created';   // Дата обнаружения
    case IndexedAt   = 'indexed';   // Последняя индексация
}
