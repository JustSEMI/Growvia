<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reading extends Model
{
    /** Satu-satunya sumber kontrak metric: sama dengan label Growvia Mobile (lib/data/reading.dart). */
    public const METRICS = [
        'temperature' => '°C',
        'humidity' => '%',
        'temperature2' => '°C',
        'humidity2' => '%',
        'light' => 'klux',
        'co2' => 'ppm',
        'soil_moisture' => '%',
        'soil_moisture2' => '%',
        'soil_moisture3' => '%',
        'soil_temperature' => '°C',
        'water_level' => '%',
        'pump' => 'state',
        'fan1' => 'state',
        'fan2' => 'state',
    ];

    public const NODES = ['node1', 'node2'];

    protected $fillable = ['node', 'metric', 'value', 'unit', 'recorded_at'];

    protected $casts = [
        'value' => 'float',
        'recorded_at' => 'datetime',
    ];
}
