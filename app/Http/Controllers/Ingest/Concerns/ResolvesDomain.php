<?php

namespace App\Http\Controllers\Ingest\Concerns;

use App\Enums\BannerStatus;
use App\Models\BannerConfig;
use App\Models\Domain;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesDomain
{
    protected function resolveDomain(string $uid): Domain
    {
        return Domain::where('domain_uid', $uid)->first()
            ?? throw new NotFoundHttpException('Unknown domain.');
    }

    protected function publishedBanner(Domain $domain): BannerConfig
    {
        return $domain->bannerConfigs()
            ->where('status', BannerStatus::Published)
            ->latest('version')
            ->first()
            ?? throw new NotFoundHttpException('No published banner for this domain.');
    }
}
