<?php

namespace App\Services\Verification;

interface TxtRecordResolver
{
    /**
     * Return the TXT record strings for a hostname.
     *
     * @return array<int, string>
     */
    public function resolve(string $hostname): array;
}
