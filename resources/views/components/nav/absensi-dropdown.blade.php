<div class="dropdown">
    <a href="#" class="nav-item nav-item-sky {{ request()->routeIs('presensi.*') ? 'active' : '' }}">
        <i class="fa-solid fa-calendar-check text-xs"></i> Presensi & Absensi <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-columns">
        <div class="dropdown-col">
            <a href="{{ route('presensi.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            @if (auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_ABSENSI->value))
            <a href="{{ route('presensi.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan Absensi</a>
            @endif
        </div>
        <div class="dropdown-col">
            <div class="dropdown-col-label">Presensi</div>
            <a href="{{ route('presensi.jadwal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Jadwal</a>
            <a href="{{ route('presensi.extra-shift') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user-clock"></i> Extra Shift</a>
            <a href="{{ route('presensi.hari-libur') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-flag"></i> Hari Libur & Cuti Bersama</a>
            <a href="{{ route('presensi.rekap-presensi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clipboard-list"></i> Rekap Presensi</a>
        </div>
        <div class="dropdown-col">
            <div class="dropdown-col-label">Absensi</div>
            <a href="{{ route('presensi.rekap-izin') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-lines"></i> Izin</a>
            <a href="{{ route('presensi.rekap-cuti') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-umbrella-beach"></i> Cuti</a>
            <a href="{{ route('presensi.rekap-lembur') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-business-time"></i> Lembur</a>
        </div>
    </div>
</div>
