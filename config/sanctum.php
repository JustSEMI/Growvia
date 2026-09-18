<?php

declare(strict_types=1);

return [
    // Mobile uses bearer tokens only. Browser sessions remain on the web guard.
    'stateful' => [],
    'guard' => [],
    'expiration' => 10080,
    'token_prefix' => '',
    'routes' => false,
];
