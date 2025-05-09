<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum SortType: string
{
    use EnumValues;

    case NAME   = 'name';
    case DATE   = 'date';
    case SIZE   = 'size';
    case WIDTH  = 'width';
    case HEIGHT = 'height';
    case RATIO  = 'ratio';
    case SQUARE = 'square';
    case REACTS = 'reacts';
}
