@php
    $canViewSchedule = auth()->user()->can('ViewAny:Schedule');
    $canViewExtraShift = auth()->user()->can('ViewAny:ExtraShiftAssignment');
    $canViewExtraShiftRule = auth()->user()->can('ViewAny:ExtraShiftRule');
    $canViewHolidays = auth()->user()->can('ViewAny:Holidays');
    $canViewRekapAbsensi = auth()->user()->can(\App\Enum\CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value);
    $canViewPengaturanAbsensi = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_ABSENSI->value);
@endphp

<span class="pwa-menu-grid-divider">Sistem Absensi</span>
<a href="{{ route('presensi.dashboard') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-chart-line"></i></span>
    <span class="pwa-menu-grid-label">Dashboard</span>
</a>
@if ($canViewSchedule)
<a href="{{ route('presensi.jadwal') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-calendar-days"></i></span>
    <span class="pwa-menu-grid-label">Jadwal</span>
</a>
@endif
@if ($canViewExtraShift)
<a href="{{ route('presensi.extra-shift') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-user-clock"></i></span>
    <span class="pwa-menu-grid-label">Extra Shift</span>
</a>
@endif
@if ($canViewExtraShiftRule)
<a href="{{ route('presensi.extra-shift-rule') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-right-left"></i></span>
    <span class="pwa-menu-grid-label">Aturan Extra Shift</span>
</a>
@endif
@if ($canViewHolidays)
<a href="{{ route('presensi.hari-libur') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-flag"></i></span>
    <span class="pwa-menu-grid-label">Hari Libur & Cuti Bersama</span>
</a>
@endif
@if ($canViewRekapAbsensi)
<a href="{{ route('presensi.rekap-presensi') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-clipboard-list"></i></span>
    <span class="pwa-menu-grid-label">Rekap Presensi</span>
</a>
<a href="{{ route('presensi.rekap-izin') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-file-lines"></i></span>
    <span class="pwa-menu-grid-label">Izin</span>
</a>
<a href="{{ route('presensi.rekap-cuti') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-umbrella-beach"></i></span>
    <span class="pwa-menu-grid-label">Cuti</span>
</a>
<a href="{{ route('presensi.rekap-lembur') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-business-time"></i></span>
    <span class="pwa-menu-grid-label">Lembur</span>
</a>
@endif
@if ($canViewPengaturanAbsensi)
<a href="{{ route('presensi.pengaturan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-gear"></i></span>
    <span class="pwa-menu-grid-label">Pengaturan Absensi</span>
</a>
@endif
