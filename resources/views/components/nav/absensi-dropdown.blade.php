@php
    $canViewPengaturanAbsensi = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_ABSENSI->value);
    $canViewSchedule = auth()->user()->can('ViewAny:Schedule');
    $canViewExtraShift = auth()->user()->can('ViewAny:ExtraShiftAssignment');
    $canViewHolidays = auth()->user()->can('ViewAny:Holidays');
    $canViewRekapAbsensi = auth()->user()->can(\App\Enum\CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value);
@endphp

<div class="dropdown">
    <a href="#" class="nav-item nav-item-sky {{ request()->routeIs('presensi.*') ? 'active' : '' }}">
        <i class="fa-solid fa-calendar-check text-xs"></i> Presensi & Absensi <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-columns">
        <div class="dropdown-col">
            <a href="{{ route('presensi.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            @if ($canViewPengaturanAbsensi)
            <a href="{{ route('presensi.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan Absensi</a>
            @endif
        </div>
        @if ($canViewSchedule || $canViewExtraShift || $canViewHolidays || $canViewRekapAbsensi)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Presensi</div>
            @if ($canViewSchedule)
            <a href="{{ route('presensi.jadwal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Jadwal</a>
            @endif
            @if ($canViewExtraShift)
            <a href="{{ route('presensi.extra-shift') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user-clock"></i> Extra Shift</a>
            @endif
            @if ($canViewHolidays)
            <a href="{{ route('presensi.hari-libur') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-flag"></i> Hari Libur & Cuti Bersama</a>
            @endif
            @if ($canViewRekapAbsensi)
            <a href="{{ route('presensi.rekap-presensi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clipboard-list"></i> Rekap Presensi</a>
            @endif
        </div>
        @endif
        @if ($canViewRekapAbsensi)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Absensi</div>
            <a href="{{ route('presensi.rekap-izin') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-lines"></i> Rekap Izin</a>
            <a href="{{ route('presensi.rekap-cuti') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-umbrella-beach"></i> Rekap Cuti</a>
            <a href="{{ route('presensi.rekap-lembur') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-business-time"></i> Rekap Lembur</a>
        </div>
        @endif
    </div>
</div>
