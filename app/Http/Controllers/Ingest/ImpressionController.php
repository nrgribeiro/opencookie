<?php

namespace App\Http\Controllers\Ingest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Ingest\Concerns\ResolvesDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImpressionController extends Controller
{
    use ResolvesDomain;

    /**
     * Fire-and-forget banner-shown beacon → daily aggregate (US-DASH-1).
     */
    public function __invoke(Request $request, string $domainUid): Response
    {
        $domain = $this->resolveDomain($domainUid);

        $validated = $request->validate([
            'bannerVersion' => ['required', 'integer', 'min:1'],
            'language' => ['nullable', 'string', 'max:12'],
        ]);

        $row = $domain->bannerImpressions()->firstOrCreate(
            [
                'day' => today(),
                'banner_version' => $validated['bannerVersion'],
                'language' => $validated['language'] ?? 'en',
            ],
            ['count' => 0],
        );

        $row->increment('count');

        return response()->noContent();
    }
}
