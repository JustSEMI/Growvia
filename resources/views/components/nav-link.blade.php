@props(['href', 'icon' => 'grid', 'active' => false])

<a href="{{ $href }}" data-label="{{ trim(strip_tags($slot)) }}" {{ $attributes->class(['nav-link', 'is-active' => $active]) }} @if($active) aria-current="page" @endif>
    <x-icon :name="$icon" /><span>{{ $slot }}</span>
</a>
