{{-- Nav group Rekrutmen (mobile) — dipisah dari Kepegawaian 2026-08-28. Lihat
     nav/rekrutmen-dropdown.blade.php. --}}
<span class="pwa-menu-grid-divider">Rekrutmen</span>
<a href="{{ route('rekrutmen.lowongan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-briefcase"></i></span>
    <span class="pwa-menu-grid-label">Lowongan Kerja</span>
</a>
