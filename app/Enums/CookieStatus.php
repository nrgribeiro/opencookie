<?php

namespace App\Enums;

enum CookieStatus: string
{
    case Active = 'active';
    case New = 'new';
    case NotSeen = 'not_seen';
}
