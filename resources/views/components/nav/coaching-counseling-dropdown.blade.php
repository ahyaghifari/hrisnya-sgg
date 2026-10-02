<div class="dropdown">
    <a href="#" class="nav-item nav-item-indigo {{ request()->routeIs(['coaching', 'coaching.create', 'coaching.show', 'counseling.kelola*', 'sp', 'sp.show', 'ba.create', 'ba.show', 'sp.rekomendasi', 'pembinaan.pengaturan']) ? 'active' : '' }}">
        <i class="fa-solid fa-comment-dots text-xs"></i> Pembinaan Karyawan <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu">
        <a href="{{ route('coaching') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clipboard-check"></i> Coaching</a>
        <a href="{{ route('counseling.kelola') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-comments"></i> Counseling</a>
        <a href="{{ route('sp') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-triangle-exclamation"></i> Surat Peringatan</a>
        {{-- Rekomendasi SP dinonaktifkan global sementara (instruksi user 2026-09-23,
        SpService::recommendationCreationEnabled) - halaman review-nya ikut disembunyikan dari
        nav, tidak ada yang perlu direview selama jalur bikin rekomendasi baru ditutup. --}}
        @if (\App\Services\Sp\SpService::recommendationCreationEnabled())
            <a href="{{ route('sp.rekomendasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clipboard-list"></i> Review Rekomendasi SP</a>
        @endif
        <a href="{{ route('pembinaan.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan Pembinaan</a>
    </div>
</div>
