<?php

namespace App\Enums;

enum AccessLevel: string {
    case None = 'none';
    case AsGuest = 'guest';
    case AsAllowedUser = 'user';
    case AsAdmin = 'admin';
}
