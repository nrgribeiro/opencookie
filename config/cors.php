<?php

return [
    /*
     * Public Consent Ingest API (technical-spec §6.1). The SDK is loaded from any
     * customer origin, so config reads and consent writes must be cross-origin.
     * No credentials are used (pseudonymous consent IDs only).
     */
    'paths' => ['v1/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,
];
