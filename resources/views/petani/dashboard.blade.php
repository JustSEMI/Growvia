@extends('layouts.petani')
@section('title', 'Kebun saya - Growvia')
@section('page', 'Kebun saya')

@section('content')
<x-page-header title="Kebun saya." description="Ringkasan kondisi terbaru dari dua area rumah kaca, disegarkan tiap 15 detik.">
    <x-slot:eyebrow>Panel petani / Ringkasan</x-slot:eyebrow>
    <x-slot:actions>
        @if($live)
            <x-badge tone="green" :dot="true">Live</x-badge>
        @elseif($lastRecorded)
            <x-badge :dot="true">Terakhir {{ \Illuminate\Support\Carbon::parse($lastRecorded)->timezone(config('app.timezone'))->format('d/m H:i T') }}</x-badge>
        @else
            <x-badge>Menunggu data</x-badge>
        @endif
    </x-slot:actions>
</x-page-header>

<turbo-frame id="petani-live" refresh="morph" data-turbo-refresh-interval="15000">
    @if(! $lastRecorded)
        <x-connection-notice />
    @endif
    <div class="stack">
        @foreach(['node1' => ['title' => 'Device 1 · Iklim', 'route' => 'petani.node1'], 'node2' => ['title' => 'Device 2 · Tanah & air', 'route' => 'petani.node2']] as $node => $meta)
            <section aria-label="{{ $meta['title'] }}">
                <div class="section-heading">
                    <h2>{{ $meta['title'] }}</h2>
                    <a class="text-link" href="{{ route($meta['route']) }}">Detail <x-icon name="arrow" /></a>
                </div>
                <div class="metrics-grid">
                    @foreach($tiles[$node] as $tile)
                        <x-metric-tile
                            :label="$tile['label']"
                            :value="$tile['value']"
                            :unit="$tile['unit']"
                            :icon="$tile['icon']"
                            :tone="$tile['tone']"
                            :note="$tile['note']" />
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</turbo-frame>
@endsection
