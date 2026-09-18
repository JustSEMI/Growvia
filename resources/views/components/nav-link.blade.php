@props(['href', 'icon' => 'grid', 'active' => false])

<a href="{{ $href }}" {{ $attributes->class(['nav-link', 'is-active' => $active]) }} @if($active) aria-current="page" @endif>
    <x-icon :name="$icon" /><span>{{ $slot }}</span>
</a>
