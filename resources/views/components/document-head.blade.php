@props(['description' => 'Growvia, ruang kerja pemantauan rumah kaca.'])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="{{ $description }}">
<meta name="theme-color" content="#f7f6f3">
<meta name="color-scheme" content="light dark">
<meta name="turbo-cache-control" content="no-cache">
<x-sidebar-bootstrap />
<x-theme-bootstrap />
<title>@yield('title', 'Growvia — Pemantauan rumah kaca')</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
@fonts
@vite(['resources/css/app.css', 'resources/js/turbo.js', 'resources/js/app.js'])
