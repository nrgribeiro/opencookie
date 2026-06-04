<?php

namespace App\Services\Scanner;

use App\Enums\CookieType;

/**
 * A single cookie/tracker detected during a scan, before classification.
 */
class DetectedCookie
{
    public function __construct(
        public readonly string $name,
        public readonly CookieType $type,
        public readonly ?string $sourceDomain = null,
        public readonly bool $isFirstParty = true,
        public readonly ?string $expiry = null,
        public readonly ?string $provider = null,
    ) {}
}
