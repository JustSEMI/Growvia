@props([
    'label',
    'ariaLabel',
    'stateText',
    'checked' => false,
    'node',
    'actuator',
    'mode' => 'manual',
    'nextState' => null,
    'action' => null,
])

<form method="POST" action="{{ $action ?? route('dashboard.control') }}" class="device-switch-form" data-turbo-frame="_top">
    @csrf
    <input type="hidden" name="node" value="{{ $node }}">
    <input type="hidden" name="actuator" value="{{ $actuator }}">
    <input type="hidden" name="mode" value="{{ $mode }}">
    @if($nextState !== null)
        <input type="hidden" name="state" value="{{ $nextState }}">
    @endif
    <button
        type="submit"
        class="switch-row"
        role="switch"
        aria-checked="{{ $checked ? 'true' : 'false' }}"
        aria-label="{{ $ariaLabel }}">
        <span class="switch-copy">
            <span class="switch-label">{{ $label }}</span>
            <span class="switch-state {{ $checked ? 'is-on' : '' }}">{{ $stateText }}</span>
        </span>
        <span class="switch-track {{ $checked ? 'is-on' : '' }}" aria-hidden="true">
            <span class="switch-thumb"></span>
        </span>
    </button>
</form>
