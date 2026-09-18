<!DOCTYPE html>
<html lang="id">
<head><x-document-head description="Growvia — informasi halaman dan akses ruang kerja." /></head>
<body>
<main class="error-shell" id="main-content">
    <div class="error-tools"><x-theme-select /></div>
    <x-brand :compact="true" />
    <p class="error-code">@yield('code')</p>
    <h1 class="error-title">@yield('title')</h1>
    <p class="error-description">@yield('message')</p>
    <div class="error-actions"><a class="button" href="{{ route('home') }}">Ke beranda <x-icon name="arrow" /></a><a class="button button--secondary" href="{{ route('login') }}">Halaman masuk</a></div>
    <p class="auth-footer mt-12">Growvia / Ruang kerja rumah kaca</p>
</main>
<x-notifications />
</body>
</html>
