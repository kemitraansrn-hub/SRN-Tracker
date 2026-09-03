@if ($paginator->hasPages())
    <nav style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div style="font-size:12px; color:var(--ink-muted);">
            Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }} &middot; {{ $paginator->total() }} data
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
            @if ($paginator->onFirstPage())
                <span class="btn" style="width:auto; opacity:0.4; cursor:default;">&larr; Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn" style="width:auto; text-decoration:none;">&larr; Sebelumnya</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span style="padding:0 4px; color:var(--ink-faint); font-size:12px;">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-primary" style="width:auto; padding:9px 13px;">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="btn" style="width:auto; padding:9px 13px; text-decoration:none;">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn" style="width:auto; text-decoration:none;">Berikutnya &rarr;</a>
            @else
                <span class="btn" style="width:auto; opacity:0.4; cursor:default;">Berikutnya &rarr;</span>
            @endif
        </div>
    </nav>
@endif
