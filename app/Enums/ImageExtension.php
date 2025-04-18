<?php

namespace App\Enums;

enum ImageExtension: string {
    case JPG  = 'jpg';
    case JPEG = 'jpeg';
    case PNG  = 'png';
    case APNG = 'apng';
    case GIF  = 'gif';
    case WEBP = 'webp';
    case AVIF = 'avif';
    case BMP  = 'bmp';
}
