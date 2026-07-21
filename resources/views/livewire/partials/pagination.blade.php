@if ($paginator->hasPages())
    {{-- wire:loading.class di <nav> ini menonaktifkan link paginasi SELAMA komponen masih
    memproses request Livewire lain (mis. klik filter Status/Perusahaan/Cari) — link paginasi
    di sini pakai wire:navigate (full page-visit SPA), yang kalau diklik SAAT masih ada request
    wire:click/wire:model lain dari komponen yang sama masih pending, race dengan mekanisme
    navigate.js Livewire dan bisa berujung error "GET method not allowed for livewire/update"
    (root cause ada di Livewire, bukan kode aplikasi — lihat catatan serupa di layout.blade.php).
    Menonaktifkan klik selama window itu mencegah race-nya kejadian sama sekali, tidak cuma
    menahan gejalanya. --}}
    <nav class="app-pager" wire:loading.class="app-pager-busy">
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
