@php
    $canViewSikartap = auth()->user()->can('ViewAny:Sikartap');
    $canViewPengaturan = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value);
    $canViewOrientasi = auth()->user()->can('ViewAny:OrientationSubmission');
    $canViewPerpanjanganKontrak = auth()->user()->can('ViewAny:ContractSubmission');
    $hasSikartap = $canViewSikartap || auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_SIKARTAP->value);
    $canViewDashboard = $hasSikartap && auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_MODUL->value);
@endphp

<span class="pwa-menu-grid-divider">Sikartap</span>
@if ($canViewOrientasi)
<a href="{{ route('sikartap.orientasi') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-user-graduate"></i></span>
    <span class="pwa-menu-grid-label">Orientasi</span>
</a>
@endif
@if ($canViewPerpanjanganKontrak)
<a href="{{ route('sikartap.kontrak') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-file-signature"></i></span>
    <span class="pwa-menu-grid-label">Perpanjangan Kontrak</span>
</a>
@endif
@if ($canViewDashboard)
<a href="{{ route('sikartap.dashboard') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-list-check"></i></span>
    <span class="pwa-menu-grid-label">Karyawan Tetap</span>
</a>
@endif
@if ($canViewSikartap)
    <a href="{{ route('sikartap.tes') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-pen-ruler"></i></span>
        <span class="pwa-menu-grid-label">Tes</span>
    </a>
    <a href="{{ route('sikartap.wawancara') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-comments"></i></span>
        <span class="pwa-menu-grid-label">Wawancara</span>
    </a>
@endif
@if ($hasSikartap)
<a href="{{ route('sikartap.ide-inovasi.atasan.queue') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-lightbulb"></i></span>
    <span class="pwa-menu-grid-label">Ide Inovasi</span>
</a>
@endif
@if ($canViewPengaturan)
    <a href="{{ route('sikartap.pengaturan') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-gear"></i></span>
        <span class="pwa-menu-grid-label">Pengaturan</span>
    </a>
@endif
