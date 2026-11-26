@props([
    'reading' => null,
    'settings' => [],
    'action',
    'hintId' => 'pump-control-hint',
])

<x-actuator-control
    node="node2"
    actuator="pump"
    actuator-label="Pompa"
    device-label="Device 2"
    :reading="$reading"
    :settings="$settings"
    :action="$action"
    :hint-id="$hintId"
    :show-auto-switch="true"
    auto-note="Mode otomatis mengikuti ambang tanah dan tandon."
    manual-note="Mode manual aktif · pompa dikendalikan langsung." />
