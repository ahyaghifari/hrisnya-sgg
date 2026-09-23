<span class="pwa-menu-grid-divider">Pembinaan Karyawan</span>
<a href="{{ route('coaching') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-clipboard-check"></i></span>
    <span class="pwa-menu-grid-label">Coaching</span>
</a>
<a href="{{ route('counseling.kelola') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-comments"></i></span>
    <span class="pwa-menu-grid-label">Counseling</span>
</a>
<a href="{{ route('sp') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-triangle-exclamation"></i></span>
    <span class="pwa-menu-grid-label">Surat Peringatan</span>
</a>
@if (\App\Services\Sp\SpService::recommendationCreationEnabled())
    <a href="{{ route('sp.rekomendasi') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-clipboard-list"></i></span>
        <span class="pwa-menu-grid-label">Review Rekomendasi SP</span>
    </a>
@endif
<a href="{{ route('pembinaan.pengaturan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-gear"></i></span>
    <span class="pwa-menu-grid-label">Pengaturan Pembinaan</span>
</a>
