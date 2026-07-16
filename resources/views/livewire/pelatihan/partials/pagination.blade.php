@if ($paginator->hasPages())
    <nav class="pelatihan-pager">
        @if ($paginator->onFirstPage())
            <span class="pelatihan-pager-btn is-disabled"><i class="fa-solid fa-chevron-left"></i> Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" wire:navigate class="pelatihan-pager-btn"><i class="fa-solid fa-chevron-left"></i> Sebelumnya</a>
        @endif

        <span class="pelatihan-pager-info">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" wire:navigate class="pelatihan-pager-btn">Berikutnya <i class="fa-solid fa-chevron-right"></i></a>
        @else
            <span class="pelatihan-pager-btn is-disabled">Berikutnya <i class="fa-solid fa-chevron-right"></i></span>
        @endif
    </nav>
@endif
