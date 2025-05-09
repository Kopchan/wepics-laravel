<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum ImageExtension: string
{
    use EnumValues;

    case JPG  = 'jpg';
    case JPEG = 'jpeg';
    case PNG  = 'png';
    case APNG = 'apng';
    case GIF  = 'gif';
    case WEBP = 'webp';
    case AVIF = 'avif';
    case BMP  = 'bmp';
}
