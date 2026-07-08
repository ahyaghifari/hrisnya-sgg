@php
    $canAccessAdmin = auth()->user()?->hasRole(['Admin', 'Super Admin']) ?? false;
    $canSeeKaryawanMenu = auth()->user()->canAccessKaryawanMenu();
    $canSubmitAttendance = ! auth()->user()->canBrowseAllCompanies();
@endphp

<div id="pwa-sidebar-backdrop" class="pwa-sidebar-backdrop" onclick="togglePwaSidebar()"></div>

<aside id="pwa-sidebar" class="pwa-sidebar">
    <div class="pwa-sidebar-header">
        <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" style="height:34px;width:auto;">
        <span style="font-size:13.5px;font-weight:700;color:#1e293b;">HRIS</span>
    </div>

    <div class="pwa-sidebar-body">
        <a href="{{ route('dashboard') }}" wire:navigate class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fa-solid fa-house text-xs w-4"></i> Dashboard
        </a>
        <a href="{{ route('data-diri') }}" wire:navigate class="nav-item {{ request()->routeIs('data-diri') ? 'active' : '' }}">
            <i class="fa-solid fa-id-card text-xs w-4"></i> Data Diri
        </a>
        <a href="{{ route('akun') }}" wire:navigate class="nav-item {{ request()->routeIs('akun') ? 'active' : '' }}">
            <i class="fa-solid fa-user text-xs w-4"></i> Akun
        </a>
        <a href="{{ route('akun.ubah-password') }}" wire:navigate class="nav-item {{ request()->routeIs('akun.ubah-password') ? 'active' : '' }}">
            <i class="fa-solid fa-lock text-xs w-4"></i> Ubah Password
        </a>
        <hr style="border:none;border-top:1px solid #f1f5f9;margin:4px 0;">
        @if ($canSeeKaryawanMenu)
            <a href="{{ route('my-staff') }}" wire:navigate class="nav-item {{ request()->routeIs('my-staff*') ? 'active' : '' }}">
                <i class="fa-solid fa-users text-xs w-4"></i> MyStaff
            </a>
            <hr style="border:none;border-top:1px solid #f1f5f9;margin:4px 8px;">
        @endif
        @if ($canSubmitAttendance)
            <a href="{{ route('my-attendance-requests') }}" wire:navigate class="nav-item {{ request()->routeIs('my-attendance-requests*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines text-xs w-4"></i> Pengajuan Absensi
            </a>
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
</aside>

<script>
    function togglePwaSidebar() {
        document.getElementById('pwa-sidebar').classList.toggle('open');
        document.getElementById('pwa-sidebar-backdrop').classList.toggle('open');
    }
</script>
