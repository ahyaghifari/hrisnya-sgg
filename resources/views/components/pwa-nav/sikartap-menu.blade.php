@php
    $canViewSikartap = auth()->user()->can('ViewAny:Sikartap');
    $canInputMcu = auth()->user()->canInputSikartapMcu()
        || auth()->user()->can(\App\Enum\CustomPermissionEnum::EDIT_INPUT_MCU->value);
    $canViewPengaturan = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value);
@endphp

<span class="pwa-menu-grid-divider">Sikartap</span>
<a href="{{ route('sikartap.dashboard') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-list-check"></i></span>
    <span class="pwa-menu-grid-label">Pengajuan</span>
</a>
@if ($canViewSikartap)
    <a href="{{ route('sikartap.jadwal') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-calendar-days"></i></span>
        <span class="pwa-menu-grid-label">Jadwal Tes &amp; Wawancara</span>
    </a>
@endif
<a href="{{ route('sikartap.ide-inovasi.atasan.queue') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-lightbulb"></i></span>
    <span class="pwa-menu-grid-label">Ide Inovasi</span>
</a>
@if ($canInputMcu)
    <a href="{{ route('sikartap.mcu.queue') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-stethoscope"></i></span>
        <span class="pwa-menu-grid-label">Antrian MCU</span>
    </a>
@endif
@if ($canViewPengaturan)
    <a href="{{ route('sikartap.pengaturan') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-gear"></i></span>
        <span class="pwa-menu-grid-label">Pengaturan</span>
    </a>
@endif
