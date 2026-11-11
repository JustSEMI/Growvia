@props(['href' => null])

<div {{ $attributes->class(['connection-banner']) }}>
    <x-icon name="info" />
    <div><strong>Sensor belum tersambung.</strong> Angka, grafik, dan status perangkat belum bisa ditampilkan dulu. Tenang, kita nggak mengarang data apa pun.</div>
    @if($href)<a class="text-link" href="{{ $href }}">Pengaturan <x-icon name="arrow" /></a>@endif
</div>
