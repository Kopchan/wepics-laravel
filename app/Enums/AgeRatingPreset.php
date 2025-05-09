<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum AgeRatingPreset: string
{
    use EnumValues;

    case SHOW = 'show';
    case BLUR = 'blur';
    case SIZE = 'hide';
}
