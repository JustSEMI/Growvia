<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ActivityLog;
use App\Support\MobileSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $allowed = array_intersect_key(ActivityLog::fieldLabels(), array_flip(array_merge(['name', 'email', 'role', 'is_active'], ...array_values(MobileSettings::SECTIONS))));
        $fields = [];
        foreach ($this->fields ?? [] as $key => $field) {
            if (is_int($key) && is_string($field) && isset($allowed[$field])) {
                $fields[] = $allowed[$field];
            }
        }

        return [
            'id' => $this->id,
            'action' => $this->action,
            'label' => ActivityLog::actionLabels()[$this->action] ?? 'Aktivitas',
            'actor_name' => $this->actor_name,
            'subject_name' => $this->subject_name,
            'fields' => array_values(array_unique($fields)),
            'created_at' => $this->created_at->utc()->toIso8601String(),
        ];
    }
}
