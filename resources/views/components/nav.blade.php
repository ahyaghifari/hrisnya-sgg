@props(['gates'])
@php
    extract($gates);
    $showRoleRow = $canSeeKaryawanMenu || $canAccessKeuangan || $canAccessAbsensi || $canAccessKepegawaian || $canAccessLms || $canAccessPenilaian || $canAccessHakAkses || $canAccessCoachingCounseling || $canAccessSikartap || $canAccessRekrutmen || $canAccessMcu;
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
            <img src="{{ asset('assets/app-icon.png') }}" alt="Syifa Global Group" class="nav-brand-icon-light" style="height:48px;width:auto;">
            <img src="{{ asset('assets/app-icon-dark-v2.png') }}" alt="Syifa Global Group" class="nav-brand-icon-dark" style="height:48px;width:auto;">
        </a>

        <div class="nav-middle">
        {{-- Baris 1: milik karyawan sendiri. Beranda selalu bebas akses; Tugas, Pelatihan,
             Pengajuan Absensi, dan Counseling+Coaching Saya masing-masing digerbangi permission
             sendiri (lihat variabel $canAccess*Saya di atas). --}}
            <div class="nav-links">
                <a href="{{ route('dashboard') }}" wire:navigate class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-house text-xs"></i> Beranda
                </a>
                @if ($canAccessTugasSaya)
                    <a href="{{ route('tugas') }}" wire:navigate class="nav-item {{ request()->routeIs('tugas*') ? 'active' : '' }}">
                        <i class="fa-solid fa-table-columns text-xs"></i> Tugas
                    </a>
                @endif
                @if ($canAccessPelatihanSaya)
                    <a href="{{ route('pelatihan') }}" wire:navigate class="nav-item {{ request()->routeIs('pelatihan*') ? 'active' : '' }}">
                        <i class="fa-solid fa-graduation-cap text-xs"></i> Pelatihan
                    </a>
                @endif
                @if ($canAccessPengajuanAbsensiSaya && $canSubmitAttendance)
                    <a href="{{ route('my-attendance-requests') }}" wire:navigate class="nav-item {{ request()->routeIs('my-attendance-requests*') ? 'active' : '' }}">
                        <i class="fa-solid fa-file-lines text-xs"></i> Pengajuan Absensi
                    </a>
                @endif
                @if ($canAccessTukarShift)
                    <a href="{{ route('tukar-shift') }}" wire:navigate class="nav-item {{ request()->routeIs('tukar-shift*') ? 'active' : '' }}">
                        <i class="fa-solid fa-right-left text-xs"></i> Tukar Shift
                    </a>
                @endif
                @if ($canAccessPembinaanSaya)
                    <div class="dropdown">
                        <a href="#" class="nav-item {{ request()->routeIs(['counseling*', 'coaching.saya*']) ? 'active' : '' }}">
                            <i class="fa-solid fa-comment-dots text-xs"></i> Pembinaan Saya <i class="fa-solid fa-chevron-down text-[10px]"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a href="{{ route('coaching.saya') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user-check"></i> Coaching Saya</a>
                            <a href="{{ route('counseling') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-comment-dots"></i> Counseling Saya</a>
                        </div>
                    </div>
                @endif
                {{-- "Status Saya" (Orientasi / Perpanjangan Kontrak / Karyawan Tetap) dipindah
                     ke Profil Saya → kartu Status Kepegawaian → tombol aksi sesuai status
                     berjalan (2026-09-08). --}}
            </div>
            @if ($showRoleRow)
                <div class="topnav-row2">
                        @if ($canSeeKaryawanMenu)
                            <x-nav.atasan-menu />
                        @endif
                        @if ($canAccessKeuangan)
                            <x-nav.keuangan-dropdown />
                        @endif
                        @if ($canAccessAbsensi)
                            <x-nav.absensi-dropdown />
                        @endif
                        @if ($canAccessKepegawaian)
                            <x-nav.kepegawaian-dropdown />
                        @endif
                        @if ($canAccessRekrutmen)
                            <x-nav.rekrutmen-dropdown />
                        @endif
                        @if ($canAccessSikartap)
                            <x-nav.sikartap-dropdown />
                        @endif
                        @if ($canAccessMcu)
                            <x-nav.mcu-dropdown />
                        @endif
                        @if ($canAccessLms)
                            <x-nav.lms-dropdown />
                        @endif
                        @if ($canAccessCoachingCounseling)
                            <x-nav.coaching-counseling-dropdown />
                        @endif
                        @if ($canAccessPenilaian)
                            <x-nav.penilaian-dropdown />
                        @endif
                        @if ($canAccessHakAkses)
                            <x-nav.manajemen-pengguna-dropdown />
                        @endif
                </div>
            @endif
        </div>

        <div class="nav-right">
            <x-theme-toggle variant="icon" />
            <livewire:notification-bell variant="desktop" :key="'notif-bell-desktop'" />
            <div class="dropdown">
                @if ($navPhotoUrl)
                    <img src="{{ $navPhotoUrl }}" alt="{{ auth()->user()->name }}" class="nav-avatar" style="object-fit:cover;">
                @else
                    <div class="nav-avatar">{{ $initials }}</div>
                @endif
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="{{ route('akun') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user"></i> Akun</a>
                    <a href="{{ route('profil-saya') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-id-card"></i> Profil Saya</a>
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
