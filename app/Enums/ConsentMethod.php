<?php

namespace App\Enums;

enum ConsentMethod: string
{
    case AcceptAll = 'accept_all';
    case RejectAll = 'reject_all';
    case Custom = 'custom';
}
