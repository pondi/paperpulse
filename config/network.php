<?php

use Illuminate\Http\Request;

return [
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
    'forwarded_headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO,
    'trusted_hosts' => array_values(array_filter(array_map(
        fn (string $host): string => '^'.preg_quote(trim($host), '/').'$',
        explode(',', env('TRUSTED_HOSTS') ?: (parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost')),
    ))),
];
