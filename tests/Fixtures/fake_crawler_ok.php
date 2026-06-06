<?php

// Test double for scanner/crawl.mjs — emits canned crawl JSON, ignores argv.
fwrite(STDOUT, json_encode([
    'pagesCrawled' => 12,
    'items' => [
        ['name' => '_ga', 'type' => 'http', 'sourceDomain' => 'example.com', 'isFirstParty' => true, 'expiry' => '2027-01-01T00:00:00.000Z', 'provider' => null],
        ['name' => '_fbp', 'type' => 'http', 'sourceDomain' => 'facebook.com', 'isFirstParty' => false, 'expiry' => 'session', 'provider' => null],
        ['name' => 'connect.facebook.net', 'type' => 'script', 'sourceDomain' => 'connect.facebook.net', 'isFirstParty' => false, 'expiry' => null, 'provider' => null],
        ['name' => 'cmp_pref', 'type' => 'local_storage', 'sourceDomain' => 'example.com', 'isFirstParty' => true, 'expiry' => 'persistent', 'provider' => null],
        ['name' => '', 'type' => 'http', 'sourceDomain' => 'example.com'],
    ],
]));
