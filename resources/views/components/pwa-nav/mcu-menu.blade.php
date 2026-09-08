{{-- Modul MCU berdiri sendiri (2026-09-08), lihat components/nav/mcu-dropdown.blade.php. --}}
<span class="pwa-menu-grid-divider">MCU</span>
<a href="{{ route('sikartap.mcu.queue') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal" style="background:#98782d"><i class="fa-solid fa-user-check"></i></span>
    <span class="pwa-menu-grid-label">Antrian Sikartap</span>
</a>
<a href="{{ route('rekrutmen.mcu.queue') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal" style="background:#98782d"><i class="fa-solid fa-briefcase"></i></span>
    <span class="pwa-menu-grid-label">Antrian Rekrutmen</span>
</a>
