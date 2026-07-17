@if ($paginator->hasPages())
    <nav class="app-pager">
        @if ($paginator->onFirstPage())
            <span class="app-pager-btn is-disabled"><i class="fa-solid fa-chevron-left"></i> <span class="app-pager-btn-label">Sebelumnya</span></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" wire:navigate class="app-pager-btn"><i class="fa-solid fa-chevron-left"></i> <span class="app-pager-btn-label">Sebelumnya</span></a>
        @endif

        <span class="app-pager-info">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" wire:navigate class="app-pager-btn"><span class="app-pager-btn-label">Berikutnya</span> <i class="fa-solid fa-chevron-right"></i></a>
        @else
            <span class="app-pager-btn is-disabled"><span class="app-pager-btn-label">Berikutnya</span> <i class="fa-solid fa-chevron-right"></i></span>
        @endif
    </nav>
@endif
