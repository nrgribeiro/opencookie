<?php

namespace App\Enums;

enum VerificationMethod: string
{
    case DnsTxt = 'dns_txt';
    case MetaTag = 'meta_tag';
    case File = 'file';
}
