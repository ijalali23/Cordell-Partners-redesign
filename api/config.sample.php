<?php
/**
 * Cordell Partners - application endpoint configuration (sample).
 *
 * Copy this file to `config.php` in the same directory and fill in the values.
 * `config.php` is git-ignored and must never be committed - it holds the Atlas
 * API token.
 *
 * The token can also be supplied through the ATLAS_API_TOKEN environment
 * variable (preferred on hosts that support it), in which case the value here
 * is only a fallback.
 */

return [
    // Atlas Open API bearer token. Leave empty to force use of the
    // ATLAS_API_TOKEN environment variable instead.
    'atlas_token' => '',

    // Atlas Open API base URL. Normally left as-is.
    'atlas_base' => 'https://api.recruitwithatlas.com',

    // Browser origins allowed to POST to this endpoint (CORS).
    // Add every domain the site is served from. Same-origin requests do not
    // need an entry, but listing them is harmless.
    'allowed_origins' => [
        'https://cordell-partners.com',
        'https://www.cordell-partners.com',
        'https://ijalali23.github.io',
    ],

    // Maximum CV upload size in bytes (8 MB).
    'max_cv_bytes' => 8 * 1024 * 1024,

    // Optional: path to a writable file used for simple per-IP rate limiting.
    // Set to null to disable. Must be writable by the web server user.
    'rate_limit_file' => null,

    // Max submissions allowed per IP within the window below.
    'rate_limit_max' => 5,
    'rate_limit_window_sec' => 3600,
];
