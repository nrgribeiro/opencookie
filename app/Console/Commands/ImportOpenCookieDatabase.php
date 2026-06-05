<?php

namespace App\Console\Commands;

use App\Enums\CookieCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Import / refresh the in-house cookie classification DB from the Open Cookie
 * Database (functional-spec §8). Reads a local CSV (--path) or downloads the
 * published CSV (--url, defaults to the upstream raw file).
 *
 *   php artisan cookies:import-ocd
 *   php artisan cookies:import-ocd --path=storage/app/open-cookie-database.csv
 */
class ImportOpenCookieDatabase extends Command
{
    protected $signature = 'cookies:import-ocd
        {--path= : Local CSV file path}
        {--url= : CSV URL (defaults to the upstream Open Cookie Database)}';

    protected $description = 'Import the Open Cookie Database into the cookie classification table';

    private const DEFAULT_URL = 'https://raw.githubusercontent.com/jkwakman/Open-Cookie-Database/master/open-cookie-database.csv';

    public function handle(): int
    {
        $csv = $this->loadCsv();
        if ($csv === null) {
            return self::FAILURE;
        }

        $rows = $this->parse($csv);
        if ($rows === []) {
            $this->error('No rows parsed from CSV.');

            return self::FAILURE;
        }

        $now = now();
        $imported = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            foreach ($chunk as $row) {
                DB::table('cookie_classifications')->updateOrInsert(
                    ['name' => $row['name'], 'domain' => $row['domain']],
                    $row + ['source' => 'ocd', 'updated_at' => $now, 'created_at' => $now],
                );
                $imported++;
            }
        }

        $this->info("Imported/updated {$imported} cookie classifications.");

        return self::SUCCESS;
    }

    private function loadCsv(): ?string
    {
        $path = $this->option('path');
        if ($path) {
            if (! is_file($path)) {
                $this->error("File not found: {$path}");

                return null;
            }

            return (string) file_get_contents($path);
        }

        $url = $this->option('url') ?: self::DEFAULT_URL;
        $this->info("Downloading {$url} ...");

        $response = Http::timeout(60)->get($url);
        if (! $response->successful()) {
            $this->error("Download failed (HTTP {$response->status()}).");

            return null;
        }

        return $response->body();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parse(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [];
        }

        $idx = $this->columnIndex($header);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            $name = trim((string) ($line[$idx['name']] ?? ''));
            if ($name === '') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'domain' => $this->nullable($line[$idx['domain']] ?? null),
                'category' => $this->mapCategory((string) ($line[$idx['category']] ?? ''))->value,
                'provider' => $this->nullable($line[$idx['platform']] ?? null),
                'provider_url' => null,
                'purpose' => $this->nullable($line[$idx['description']] ?? null),
                'retention' => $this->nullable($line[$idx['retention']] ?? null),
                'is_wildcard' => $this->truthy($line[$idx['wildcard']] ?? null),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Resolve column positions by header name, tolerant of upstream reordering.
     *
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function columnIndex(array $header): array
    {
        $find = function (array $needles) use ($header): int {
            foreach ($header as $i => $col) {
                $c = Str::lower(trim($col));
                foreach ($needles as $n) {
                    if (str_contains($c, $n)) {
                        return $i;
                    }
                }
            }

            return -1;
        };

        return [
            'platform' => $find(['platform']),
            'category' => $find(['category']),
            'name' => $find(['key name', 'cookie /', 'data key', 'name']),
            'domain' => $find(['domain']),
            'description' => $find(['description']),
            'retention' => $find(['retention']),
            'wildcard' => $find(['wildcard']),
        ];
    }

    private function mapCategory(string $raw): CookieCategory
    {
        $c = Str::lower(trim($raw));

        return match (true) {
            str_contains($c, 'function') || str_contains($c, 'necessary') || str_contains($c, 'essential') => CookieCategory::Necessary,
            str_contains($c, 'preference') => CookieCategory::Preferences,
            str_contains($c, 'analytic') || str_contains($c, 'statistic') || str_contains($c, 'performance') => CookieCategory::Statistics,
            str_contains($c, 'marketing') || str_contains($c, 'advertis') || str_contains($c, 'targeting') => CookieCategory::Marketing,
            default => CookieCategory::Unclassified,
        };
    }

    private function nullable(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return ($v === null || $v === '') ? null : (string) $v;
    }

    private function truthy(mixed $v): bool
    {
        $v = Str::lower(trim((string) $v));

        return in_array($v, ['1', 'true', 'yes', 'y'], true);
    }
}
