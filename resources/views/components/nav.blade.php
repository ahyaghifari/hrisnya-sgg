@php
    $canAccessAdmin = auth()->user()?->hasRole(['Admin', 'Super Admin']) ?? false;
    $canSeeKaryawanMenu = auth()->user()->canAccessKaryawanMenu();
    $canSubmitAttendance = ! auth()->user()->canBrowseAllCompanies();
    $canAccessKeuangan = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value);
    $canAccessMaster = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_MASTER->value);
    $initials = collect(explode(' ', auth()->user()->name))
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');

    $navPhotoPath = auth()->user()->profile?->profile_picture[0] ?? null;
    $navPhotoUrl = $navPhotoPath && is_string($navPhotoPath) ? \Illuminate\Support\Facades\Storage::url($navPhotoPath) : null;
@endphp

<nav class="topnav">
    <div class="topnav-inner">
        <a href="{{ route('dashboard') }}" wire:navigate class="nav-brand">
            <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" style="height:38px;width:auto;">
            <span style="font-size:13.5px;font-weight:700;color:var(--text-heading);">HRIS</span>
        </a>

        <div class="nav-links">
            <a href="{{ route('dashboard') }}" wire:navigate class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-house text-xs"></i> Beranda
            </a>
            @if ($canSeeKaryawanMenu)
                <a href="{{ route('my-staff') }}" wire:navigate class="nav-item {{ request()->routeIs('my-staff*') ? 'active' : '' }}">
                    <i class="fa-solid fa-users text-xs"></i> MyStaff
                </a>
            @endif
            <div class="dropdown">
                <a href="#" class="nav-item {{ request()->routeIs(['kanban*', 'pelatihan*']) ? 'active' : '' }}">
                    <i class="fa-solid fa-list-check text-xs"></i> Penugasan <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="{{ route('kanban') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-table-columns"></i> Kanban</a>
                    <a href="{{ route('pelatihan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-graduation-cap"></i> Pelatihan</a>
                </div>
            </div>
            @if ($canSubmitAttendance)
                <a href="{{ route('my-attendance-requests') }}" wire:navigate class="nav-item {{ request()->routeIs('my-attendance-requests*') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-lines text-xs"></i> Pengajuan Absensi
                </a>
            @endif
            @if ($canAccessKeuangan)
                <div class="dropdown">
                    <a href="#" class="nav-item {{ request()->routeIs(['keuangan.periode-gaji*', 'keuangan.payroll*', 'keuangan.komponen-gaji*', 'keuangan.insentif*', 'keuangan.mapping-komponen-gaji*']) ? 'active' : '' }}">
                        <i class="fa-solid fa-sack-dollar text-xs"></i> Penggajian <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Periode Gaji</a>
                        <a href="{{ route('keuangan.payroll') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-money-check-dollar"></i> Payroll</a>
                        <div style="height:1px;background:var(--border-soft);margin:6px 8px;"></div>
                        <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Komponen Gaji</a>
                        <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-diagram-project"></i> Mapping Komponen Gaji</a>
                        <a href="{{ route('keuangan.insentif') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hand-holding-dollar"></i> Insentif</a>
                    </div>
                </div>
            @endif
            @if ($canAccessMaster)
                <div class="dropdown">
                    <a href="#" class="nav-item {{ request()->routeIs('master.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-database text-xs"></i> Master <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="{{ route('master.hari-libur') }}" wire:navigate class="dropdown-item">Hari Libur & Cuti Bersama</a>
                    </div>
                </div>
            @endif
        </div>

        <div class="nav-right">
            <x-theme-toggle variant="icon" />
            {{-- Tombol notifikasi disembunyikan sementara --}}
            {{-- <div class="nav-bell"><i class="fa-regular fa-bell text-sm"></i><span class="nav-bell-dot"></span></div> --}}
            <div class="dropdown">
                @if ($navPhotoUrl)
                    <img src="{{ $navPhotoUrl }}" alt="{{ auth()->user()->name }}" class="nav-avatar" style="object-fit:cover;">
                @else
                    <div class="nav-avatar">{{ $initials }}</div>
                @endif
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="{{ route('akun') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user"></i> Akun</a>
                    <a href="{{ route('data-diri') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-id-card"></i> Data Diri</a>
                    <a href="{{ route('akun.ubah-password') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-lock"></i> Ubah Password</a>
                    <hr style="border:none;border-top:1px solid var(--border-soft);margin:4px 0;">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>
