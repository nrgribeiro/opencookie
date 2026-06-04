<?php

namespace App\Services\Scanner;

use App\Models\Domain;

interface SiteScanner
{
    /**
     * Crawl the domain (up to $pageLimit pages) and return detected cookies/trackers.
     */
    public function scan(Domain $domain, int $pageLimit): ScanResult;
}
