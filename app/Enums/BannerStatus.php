<?php

namespace App\Enums;

enum BannerStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
