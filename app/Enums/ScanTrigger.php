<?php

namespace App\Enums;

enum ScanTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
}
