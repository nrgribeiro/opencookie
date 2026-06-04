<?php

namespace App\Enums;

enum CookieType: string
{
    case Http = 'http';
    case Script = 'script';
    case LocalStorage = 'local_storage';
    case SessionStorage = 'session_storage';
    case Pixel = 'pixel';
}
