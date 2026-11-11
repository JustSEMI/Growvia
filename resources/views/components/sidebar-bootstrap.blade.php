<script data-sidebar-bootstrap @if(! empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
    (() => {
        let collapsed = false;
        try {
            collapsed = localStorage.getItem('growvia.sidebar') === 'collapsed';
        } catch {}
        document.documentElement.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
    })();
</script>
