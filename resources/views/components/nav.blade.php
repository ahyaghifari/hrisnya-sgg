@php
    $canAccessAdmin = auth()->user()?->hasRole(['Admin', 'Super Admin']) ?? false;
    $canSeeKaryawanMenu = auth()->user()->canAccessKaryawanMenu();
    $canSubmitAttendance = ! auth()->user()->canBrowseAllCompanies();
    $initials = collect(explode(' ', auth()->user()->name))
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<nav class="topnav">
    <div class="topnav-inner">
        <a href="{{ route('dashboard') }}" wire:navigate class="nav-brand">
            <div class="nav-logo">S</div>
            <div>
                <span class="nav-brand-name">Syifa Global</span>
                <span class="nav-brand-sub">Employee Portal</span>
            </div>
        </a>

        <div class="nav-links">
            <a href="{{ route('dashboard') }}" wire:navigate class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-house text-xs"></i> Dashboard
            </a>
            @if ($canSeeKaryawanMenu)
                <div class="dropdown">
                    <button type="button" class="nav-item">
                        <i class="fa-solid fa-users text-xs"></i> Karyawan <i class="fa-solid fa-chevron-down text-xs opacity-50"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="{{ route('my-employees') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-users"></i> Karyawanku</a>
                        <a href="#" class="dropdown-item"><i class="fa-solid fa-calendar-check"></i> Presensi</a>
                    </div>
                </div>
            @endif
            @if ($canSubmitAttendance)
                <div class="dropdown">
                    <button type="button" class="nav-item">
                        <i class="fa-solid fa-file-lines text-xs"></i> Pengajuan Absensi <i class="fa-solid fa-chevron-down text-xs opacity-50"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="{{ route('my-attendance-requests.leave') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-umbrella-beach"></i> Cuti</a>
                        <a href="{{ route('my-attendance-requests.permission') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clock"></i> Izin</a>
                        <a href="{{ route('my-attendance-requests.overtime') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-briefcase-clock"></i> Lembur</a>
                        <hr style="border:none;border-top:1px solid #f1f5f9;margin:4px 0;">
                        <a href="{{ route('my-attendance-requests') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-rectangle-list"></i> Riwayat</a>
                    </div>
                </div>
            @endif
        </div>

        <div class="nav-right">
            @if ($canAccessAdmin)
                <a href="{{ url('/admin') }}" target="_blank" class="nav-item" style="border:1.5px solid #e2e8f0;background:#f8fafc;">
                    <i class="fa-solid fa-table-columns text-xs"></i> Admin Panel
                </a>
            @endif
            <div class="nav-bell"><i class="fa-regular fa-bell text-sm"></i><span class="nav-bell-dot"></span></div>
            <div class="dropdown">
                <div class="nav-avatar">{{ $initials }}</div>
                <div class="dropdown-menu dropdown-menu-right">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <button type="button" class="hamburger-btn" onclick="document.getElementById('app-mobile-menu').classList.toggle('open')" aria-label="Buka menu">
            <i class="fas fa-bars text-sm"></i>
        </button>
    </div>

    <div class="mobile-menu" id="app-mobile-menu">
        <div class="mobile-menu-inner">
            <a href="{{ route('dashboard') }}" wire:navigate class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-house text-xs w-4"></i> Dashboard
            </a>
            @if ($canSeeKaryawanMenu)
                <div class="mobile-sub-label">Karyawan</div>
                <a href="{{ route('my-employees') }}" wire:navigate class="nav-item nav-item-sub {{ request()->routeIs('my-employees') ? 'active' : '' }}">
                    <i class="fa-solid fa-users text-xs w-4"></i> Karyawanku
                </a>
                <a href="#" class="nav-item nav-item-sub"><i class="fa-solid fa-calendar-check text-xs w-4"></i> Presensi</a>
                <hr style="border:none;border-top:1px solid #f1f5f9;margin:4px 8px;">
            @endif
            @if ($canSubmitAttendance)
                <div class="mobile-sub-label">Pengajuan Absensi</div>
                <a href="{{ route('my-attendance-requests.leave') }}" wire:navigate class="nav-item nav-item-sub"><i class="fa-solid fa-umbrella-beach text-xs w-4"></i> Cuti</a>
                <a href="{{ route('my-attendance-requests.permission') }}" wire:navigate class="nav-item nav-item-sub"><i class="fa-solid fa-clock text-xs w-4"></i> Izin</a>
                <a href="{{ route('my-attendance-requests.overtime') }}" wire:navigate class="nav-item nav-item-sub"><i class="fa-solid fa-briefcase-clock text-xs w-4"></i> Lembur</a>
                <hr style="border:none;border-top:1px solid #f1f5f9;margin:4px 8px;">
                <a href="{{ route('my-attendance-requests') }}" wire:navigate class="nav-item nav-item-sub"><i class="fa-solid fa-rectangle-list text-xs w-4"></i> Riwayat</a>
                <hr style="border:none;border-top:1px solid #f1f5f9;margin:4px 0;">
            @endif
            @if ($canAccessAdmin)
                <a href="{{ url('/admin') }}" target="_blank" class="nav-item" style="color:#042c6c;font-weight:600;">
                    <i class="fa-solid fa-table-columns text-xs w-4"></i> Admin Panel
                </a>
            @endif
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="nav-item w-full">
                    <i class="fa-solid fa-arrow-right-from-bracket text-xs w-4"></i> Keluar
                </button>
            </form>
        </div>
    </div>
</nav>
