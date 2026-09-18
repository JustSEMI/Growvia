@if($paginator->hasPages())
<nav class="pagination" aria-label="Navigasi halaman">
    <span class="pagination-summary">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} {{ $noun ?? 'akun' }}</span>
    @if($paginator->onFirstPage())<span data-page aria-disabled="true" aria-label="Sebelumnya">←</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">←</a>@endif
    @foreach($elements as $element)
        @if(is_string($element))<span aria-hidden="true">{{ $element }}</span>@endif
        @if(is_array($element))
            @foreach($element as $page => $url)
                @if($page == $paginator->currentPage())<span data-page aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" aria-label="Ke halaman {{ $page }}">{{ $page }}</a>@endif
            @endforeach
        @endif
    @endforeach
    @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">→</a>@else<span data-page aria-disabled="true" aria-label="Berikutnya">→</span>@endif
</nav>
@endif
