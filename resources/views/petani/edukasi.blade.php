@extends('layouts.petani')
@section('title', 'Edukasi - Growvia')
@section('page', 'Edukasi')

@section('content')
<x-page-header title="Panduan singkat." description="Panduan praktis pemantauan suhu, kelembapan, penyiraman, dan tandon kebun.">
    <x-slot:eyebrow>Panel petani / Edukasi</x-slot:eyebrow>
</x-page-header>
<section class="panel panel-body" aria-label="Daftar panduan perawatan">
    <details class="education-item" open>
        <summary><span class="education-number">01</span>Suhu &amp; ventilasi<x-icon name="chevron-down" class="education-chevron" /></summary>
        <div class="education-body"><p>Target suhu merupakan acuan konfigurasi, bukan patokan mutlak semua tanaman. Jika suhu naik melewati batas, periksa ventilasi, kipas, dan naungan.</p></div>
    </details>
    <details class="education-item">
        <summary><span class="education-number">02</span>Kelembapan udara<x-icon name="chevron-down" class="education-chevron" /></summary>
        <div class="education-body"><p>Kelembapan berlebih memicu pertumbuhan jamur. Jaga sirkulasi udara dan jarak tanam agar tetap seimbang.</p></div>
    </details>
    <details class="education-item">
        <summary><span class="education-number">03</span>Penyiraman &amp; media tanam<x-icon name="chevron-down" class="education-chevron" /></summary>
        <div class="education-body"><p>Kebutuhan air berbeda per media dan jenis tanaman. Pastikan sensor kelembapan tanah terpasang baik di area akar sebelum mengandalkan jadwal otomatis.</p></div>
    </details>
    <details class="education-item">
        <summary><span class="education-number">04</span>Intensitas cahaya<x-icon name="chevron-down" class="education-chevron" /></summary>
        <div class="education-body"><p>Kebutuhan cahaya bergantung pada fase tumbuh. Nilai nol di siang hari biasanya menandakan sensor terhalang atau kabel terlepas.</p></div>
    </details>
    <details class="education-item">
        <summary><span class="education-number">05</span>Tandon &amp; pompa<x-icon name="chevron-down" class="education-chevron" /></summary>
        <div class="education-body"><p>Periksa ketinggian air tandon dan sambungan selang secara berkala. Jangan biarkan pompa bekerja saat air berada di bawah batas minimum.</p></div>
    </details>
    <details class="education-item">
        <summary><span class="education-number">06</span>Kapan menghubungi admin<x-icon name="chevron-down" class="education-chevron" /></summary>
        <div class="education-body"><ul><li>Sensor atau kabel terlihat rusak</li><li>Tandon bocor atau pompa bermasalah</li><li>Angka pembacaan tidak berubah dalam waktu lama</li></ul></div>
    </details>
</section>
@endsection
