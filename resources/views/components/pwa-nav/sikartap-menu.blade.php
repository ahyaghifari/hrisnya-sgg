@php
    $canViewSikartap = auth()->user()->can('ViewAny:Sikartap');
    $canInputMcu = auth()->user()->canInputSikartapMcu()
        || auth()->user()->can(\App\Enum\CustomPermissionEnum::EDIT_INPUT_MCU->value);
    $canViewPengaturan = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value);
    $canViewKontrak = auth()->user()->can('ViewAny:UserContract');
    $canViewOrientasi = auth()->user()->can('ViewAny:OrientationSubmission');
    $canViewPerpanjanganKontrak = auth()->user()->can('ViewAny:ContractSubmission');
    $hasSikartap = $canViewSikartap || auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_SIKARTAP->value);
@endphp

<span class="pwa-menu-grid-divider">Sikartap</span>
@if ($canViewOrientasi)
<a href="{{ route('kepegawaian.orientasi') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-user-graduate"></i></span>
    <span class="pwa-menu-grid-label">Orientasi</span>
</a>
@endif
@if ($canViewKontrak)
<a href="{{ route('kepegawaian.kontrak') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-file-contract"></i></span>
    <span class="pwa-menu-grid-label">Kontrak</span>
</a>
@endif
@if ($canViewPerpanjanganKontrak)
<a href="{{ route('kepegawaian.perpanjangan-kontrak') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-file-signature"></i></span>
    <span class="pwa-menu-grid-label">Perpanjangan Kontrak</span>
</a>
@endif
@if ($hasSikartap)
<a href="{{ route('sikartap.dashboard') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-list-check"></i></span>
    <span class="pwa-menu-grid-label">Karyawan Tetap</span>
</a>
@endif
@if ($canViewSikartap)
    <a href="{{ route('sikartap.jadwal') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-calendar-days"></i></span>
        <span class="pwa-menu-grid-label">Jadwal Tes &amp; Wawancara</span>
    </a>
@endif
@if ($hasSikartap)
<a href="{{ route('sikartap.ide-inovasi.atasan.queue') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-lightbulb"></i></span>
    <span class="pwa-menu-grid-label">Ide Inovasi</span>
</a>
@endif
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
