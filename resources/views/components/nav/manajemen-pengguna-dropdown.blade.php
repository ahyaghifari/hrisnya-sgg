@php
    // $canAccessHakAkses (KELOLA_HAK_AKSES) sudah dipastikan true oleh nav.blade.php sebelum
    // component ini dimuat. ViewAny:Role gerbang spesifik "Role" sendiri - dihitung di sini,
    // bukan di parent, sama pola modul lain.
    $canViewRole = auth()->user()->can('ViewAny:Role');
@endphp

<div class="dropdown">
    <a href="#" class="nav-item nav-item-sky {{ request()->routeIs(['role*', 'hak-akses*', 'akses-filter-perusahaan*']) ? 'active' : '' }}">
        <i class="fa-solid fa-shield-halved text-xs"></i> Manajemen Pengguna <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu">
        @if ($canViewRole)
            <a href="{{ route('role') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user-tag"></i> Role</a>
        @endif
        <a href="{{ route('hak-akses') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-shield-halved"></i> Hak Akses</a>
        <a href="{{ route('akses-filter-perusahaan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-building-shield"></i> Akses Filter Perusahaan</a>
    </div>
</div>
