<?php

namespace App\Services\Verification;

class DnsTxtRecordResolver implements TxtRecordResolver
{
    /**
     * @return array<int, string>
     */
    public function resolve(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_TXT) ?: [];

        return collect($records)
            ->pluck('txt')
            ->filter()
            ->values()
            ->all();
    }
}
