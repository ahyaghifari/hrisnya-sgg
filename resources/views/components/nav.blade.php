@php
    $canAccessAdmin = auth()->user()?->hasRole(['Admin', 'Super Admin']) ?? false;
    $canSeeKaryawanMenu = auth()->user()->canAccessKaryawanMenu();
    $canSubmitAttendance = ! auth()->user()->canBrowseAllCompanies();
    $canAccessKeuangan = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value);
    $initials = collect(explode(' ', auth()->user()->name))
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<nav class="topnav">
    <div class="topnav-inner">
        <a href="{{ route('dashboard') }}" wire:navigate class="nav-brand">
            <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" style="height:38px;width:auto;">
            <span style="font-size:13.5px;font-weight:700;color:var(--text-heading);">HRIS</span>
        </a>

        <div class="nav-links">
            <a href="{{ route('dashboard') }}" wire:navigate class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-house text-xs"></i> Dashboard
            </a>
            @if ($canSeeKaryawanMenu)
                <a href="{{ route('my-staff') }}" wire:navigate class="nav-item {{ request()->routeIs('my-staff*') ? 'active' : '' }}">
                    <i class="fa-solid fa-users text-xs"></i> MyStaff
                </a>
            @endif
            @if ($canSubmitAttendance)
                <a href="{{ route('my-attendance-requests') }}" wire:navigate class="nav-item {{ request()->routeIs('my-attendance-requests*') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-lines text-xs"></i> Pengajuan Absensi
                </a>
            @endif
            @if ($canAccessKeuangan)
                <div class="dropdown">
                    <a href="#" class="nav-item {{ request()->routeIs(['keuangan.komponen-gaji*', 'keuangan.mapping-komponen-gaji*']) ? 'active' : '' }}">
                        <i class="fa-solid fa-sack-dollar text-xs"></i> Master Gaji <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="dropdown-item">Komponen Gaji</a>
                        <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="dropdown-item">Mapping Komponen Gaji</a>
                    </div>
                </div>
                <a href="{{ route('keuangan.insentif') }}" wire:navigate class="nav-item {{ request()->routeIs('keuangan.insentif*') ? 'active' : '' }}">
                    <i class="fa-solid fa-hand-holding-dollar text-xs"></i> Insentif
                </a>
            @endif
        </div>

        <div class="nav-right">
            @if ($canAccessAdmin)
                <a href="{{ url('/admin') }}" target="_blank" class="nav-item" style="border:1.5px solid var(--border);background:var(--surface-alt);">
                    <i class="fa-solid fa-table-columns text-xs"></i> Admin Panel
                </a>
            @endif
            <x-theme-toggle variant="icon" />
            <div class="nav-bell"><i class="fa-regular fa-bell text-sm"></i><span class="nav-bell-dot"></span></div>
            <div class="dropdown">
                <div class="nav-avatar">{{ $initials }}</div>
                <div class="dropdown-menu dropdown-menu-right">
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
