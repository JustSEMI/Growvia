@props(['href' => null])

<div {{ $attributes->class(['connection-banner']) }}>
    <x-icon name="info" />
    <div><strong>Integrasi sensor belum aktif.</strong> Nilai, grafik, dan status perangkat belum tersedia. Tidak ada data simulasi yang ditampilkan.</div>
    @if($href)<a class="text-link" href="{{ $href }}">Pengaturan <x-icon name="arrow" /></a>@endif
</div>
