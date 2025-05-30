<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum SortType: string
{
    use EnumValues;

    case Name           = 'name';
    case Date           = 'date';
    case Random         = 'random';
    case Size           = 'size';
    case Width          = 'width';
    case Height         = 'height';
    case Ratio          = 'ratio';
    case Squareness     = 'square';
    case ReactionsCount = 'reacts';
    case Duration       = 'duration';
    case FramesCount    = 'frames';
    case BitRate        = 'bitrate';
    case FrameRate      = 'framerate';
}
