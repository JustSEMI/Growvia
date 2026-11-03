@props(['href' => null])

<div {{ $attributes->class(['connection-banner']) }}>
    <x-icon name="info" />
    <div><strong>Sensornya belum nyambung.</strong> Angka, grafik, dan status perangkat belum ada. Nggak ada data bohongan di sini.</div>
    @if($href)<a class="text-link" href="{{ $href }}">Pengaturan <x-icon name="arrow" /></a>@endif
</div>
