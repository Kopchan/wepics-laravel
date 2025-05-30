<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum MediaType: string
{
    use EnumValues;

    case Image         = 'img';
    case Video         = 'vid';
    case ImageAnimated = 'anim';
    case Audio         = 'aud';
}
