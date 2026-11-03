<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Trusted Proxies
|--------------------------------------------------------------------------
|
| Leave this empty unless the application runs behind a load balancer or
| reverse proxy that terminates TLS. Only then do the X-Forwarded-* headers
| come from a source you control; trusting them unconditionally would let any
| client spoof its own scheme and IP.
|
| Configure TRUSTED_PROXIES with an explicit comma-separated list of proxy
| IPs or CIDR ranges, for example:
|
|     TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
|
| Without this, $request->secure() stays false behind a terminator and the
| API transport guard correctly rejects everything with HTTP 426.
|
| "*" is only safe behind a proxy that overwrites client-supplied
| X-Forwarded-* headers before adding its own. Laravel reads the literal
| string "*" as "trust every caller"; passing ["*"] as an array would
| instead match no IP at all and fail the transport guard.
|
*/

$proxies = array_values(array_filter(
    array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
    static fn (string $proxy): bool => $proxy !== '',
));

return [
    // null (not an empty array) keeps Laravel's own fallback intact, including
    // its automatic trust on Laravel Cloud / Forge / Vapor hosts.
    'proxies' => in_array('*', $proxies, true) ? '*' : ($proxies === [] ? null : $proxies),
];
