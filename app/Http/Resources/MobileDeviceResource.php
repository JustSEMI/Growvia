<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileDeviceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_intersect_key($this->resource, array_flip(['kind', 'label', 'device', 'ip', 'current', 'last_active', 'expires_at']));
    }
}
