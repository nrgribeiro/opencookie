<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scanner driver
    |--------------------------------------------------------------------------
    |
    | "http"       — lightweight homepage-only fetch (no JS runtime). Default;
    |                no external dependencies, used in tests/CI.
    | "playwright" — headless Chromium multi-page crawl that executes JS and
    |                captures cookies, localStorage, sessionStorage and
    |                third-party requests. Requires Node + Playwright (Chromium)
    |                in the worker environment.
    |
    */

    'driver' => env('SCANNER_DRIVER', 'http'),

    /*
    |--------------------------------------------------------------------------
    | Playwright crawler
    |--------------------------------------------------------------------------
    */

    'playwright' => [
        'node_binary' => env('SCANNER_NODE_BINARY', 'node'),
        'script' => base_path('scanner/crawl.mjs'),
        // Hard wall-clock cap for the whole crawl (seconds).
        'timeout' => (int) env('SCANNER_TIMEOUT', 180),
        // Per-page navigation timeout (milliseconds), passed to the crawler.
        'page_timeout_ms' => (int) env('SCANNER_PAGE_TIMEOUT_MS', 15000),
    ],

];
