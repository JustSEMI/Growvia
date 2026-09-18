@props(['compact' => false])

<a href="{{ route('home') }}" {{ $attributes->class(['brand']) }} aria-label="Growvia, ke beranda">
    <span class="brand-mark"><x-icon name="leaf" /></span>
    <span class="brand-type">growvia<span class="brand-dot">.</span>@unless($compact)<small>RUANG KERJA KEBUN</small>@endunless</span>
</a>
