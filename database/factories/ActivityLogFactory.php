<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => 'settings.updated',
            'actor_id' => null,
            'actor_name' => 'Sistem',
            'subject_id' => null,
            'subject_name' => null,
            'fields' => [],
            'created_at' => now(),
        ];
    }
}
