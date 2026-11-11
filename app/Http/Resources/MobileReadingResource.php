<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Reading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileReadingResource extends JsonResource
{
    /** Kontrak Growvia Mobile: Reading.fromJson menuntut id int, value num, recorded_at UTC. */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'node' => $this->node,
            'metric' => $this->metric,
            'value' => $this->value,
            'unit' => $this->unit,
            'recorded_at' => $this->recorded_at->utc()->toIso8601String(),
        ];
    }
}
