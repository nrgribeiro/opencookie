<?php

namespace App\Services\Scanner;

class ScanResult
{
    /**
     * @param  array<int, DetectedCookie>  $cookies
     */
    public function __construct(
        public readonly int $pagesCrawled,
        public readonly array $cookies,
    ) {}
}
