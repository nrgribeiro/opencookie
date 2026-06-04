<?php

namespace App\Enums;

enum ScanStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Complete = 'complete';
    case Failed = 'failed';
}
