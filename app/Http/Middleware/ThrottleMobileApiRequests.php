<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Give the mobile IP limiter its own priority without reordering web throttles.
 */
class ThrottleMobileApiRequests extends ThrottleRequests {}
