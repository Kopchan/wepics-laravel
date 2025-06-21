<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum AccessLevel: string
{
    use EnumValues;

    case None = 'none';
    case AsGuest = 'guest';
    case AsAllowedUser = 'user';
    case AsOwner = 'owner';
    case AsAdmin = 'admin';
}
