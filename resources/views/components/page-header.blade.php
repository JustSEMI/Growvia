@props(['title', 'description' => null, 'live' => null, 'updated' => null, 'livedot' => null])

<header class="page-header">
    <div>
        @isset($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endisset
        <h1 class="page-title">{{ $title }}</h1>
        @if($description)<p class="page-description">{{ $description }}</p>@endif
        @isset($extra)<div class="page-description">{{ $extra }}</div>@endisset
    </div>
    <div class="header-actions">
        @if($live)<x-badge :dot="true">{{ $live }}</x-badge>@endif
        @if($updated)<span class="panel-subtitle">{{ $updated }}</span>@endif
        @isset($actions){{ $actions }}@endisset
    </div>
</header>
