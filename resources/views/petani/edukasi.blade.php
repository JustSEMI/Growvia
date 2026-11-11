@extends('layouts.petani')
@section('title', 'Edukasi — Growvia')
@section('page', 'Edukasi')

@section('content')
<x-page-header title="Panduan singkat." description="Cukup dibaca sekali. Detail ada di mobile."><x-slot:eyebrow>Growvia / Edukasi</x-slot:eyebrow></x-page-header>
<section class="panel panel-body">
    <details class="education-item" open><summary><span class="education-number">01</span>Suhu &amp; ventilasi</summary><div class="education-body"><p>Target suhu itu settingan, bukan patokan semua tanaman. Kalau kepanasan, cek ventilasi dan naungan.</p></div></details>
    <details class="education-item"><summary><span class="education-number">02</span>Kelembapan</summary><div class="education-body"><p>Lembap berlebih bikin jamur. Sirkulasi udara dan jarak tanam membantu.</p></div></details>
    <details class="education-item"><summary><span class="education-number">03</span>Nyiram</summary><div class="education-body"><p>Kebutuhan air beda per media dan tanaman. Kalibrasi sensor tanah biar akurat. Otomatisasi belum jalan.</p></div></details>
    <details class="education-item"><summary><span class="education-number">04</span>Cahaya</summary><div class="education-body"><p>Kebutuhan cahaya beda per tahap tumbuh. Nol di siang hari bisa jadi sensor lepas.</p></div></details>
    <details class="education-item"><summary><span class="education-number">05</span>Tandon &amp; pompa</summary><div class="education-body"><p>Cek air dan selang berkala. Jangan andalkan pengaman otomatis sebelum dites.</p></div></details>
    <details class="education-item"><summary><span class="education-number">06</span>Hubungi admin</summary><div class="education-body"><ul><li>Sensor/kabel rusak</li><li>Tandon bocor / pompa bermasalah</li><li>Data tidak berubah</li></ul></div></details>
</section>
@endsection
