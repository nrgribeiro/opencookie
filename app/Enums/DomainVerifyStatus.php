<?php

namespace App\Enums;

enum DomainVerifyStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
}
