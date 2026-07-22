@php
    $canSeeKaryawanMenu = auth()->user()->canAccessKaryawanMenu();
    $canSubmitAttendance = ! auth()->user()->canBrowseAllCompanies();
    $canAccessKeuangan = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value);
    $canAccessMaster = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_MASTER->value);
    $canAccessAbsensi = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value);
    $canAccessKepegawaian = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value);
    $canAccessLms = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_LMS->value);
    // Murni permission Shield (bukan hasSubordinates() seperti MyStaff) — siapa yang boleh
    // bikin Coaching ditentukan lewat checklist role di /admin/roles, bukan otomatis dari
    // struktur organisasi. Lihat planning/2026-07-18-portal-coaching-counseling.md §3.2.
    
    $canManageCoaching = auth()->user()->can('Create:Coaching');
    $showRoleRow = $canSeeKaryawanMenu || $canAccessKeuangan || $canAccessMaster || $canAccessAbsensi || $canAccessKepegawaian || $canAccessLms || $canManageCoaching;
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
            <img src="{{ asset('assets/app-icon-dark.png') }}" alt="Syifa Global Group" class="nav-brand-icon-dark" style="height:48px;width:auto;">
        </a>

        <div class="nav-middle">
        {{-- Baris 1: milik karyawan sendiri — sama buat semua orang, tidak tergantung role/permission apa pun. --}}
            <div class="nav-links">
                <a href="{{ route('dashboard') }}" wire:navigate class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-house text-xs"></i> Beranda
                </a>
                <a href="{{ route('kanban') }}" wire:navigate class="nav-item {{ request()->routeIs('kanban*') ? 'active' : '' }}">
                    <i class="fa-solid fa-table-columns text-xs"></i> Penugasan
                </a>
                <a href="{{ route('pelatihan') }}" wire:navigate class="nav-item {{ request()->routeIs('pelatihan*') ? 'active' : '' }}">
                    <i class="fa-solid fa-graduation-cap text-xs"></i> Pelatihan
                </a>
                @if ($canSubmitAttendance)
                    <a href="{{ route('my-attendance-requests') }}" wire:navigate class="nav-item {{ request()->routeIs('my-attendance-requests*') ? 'active' : '' }}">
                        <i class="fa-solid fa-file-lines text-xs"></i> Pengajuan Absensi
                    </a>
                @endif
                <a href="{{ route('counseling') }}" wire:navigate class="nav-item {{ request()->routeIs('counseling*') ? 'active' : '' }}">
                    <i class="fa-solid fa-comment-dots text-xs"></i> Counseling
                </a>
                <a href="{{ route('coaching.saya') }}" wire:navigate class="nav-item {{ request()->routeIs('coaching.saya*') ? 'active' : '' }}">
                    <i class="fa-solid fa-user-check text-xs"></i> Coaching Saya
                </a>
            </div>
            @if ($showRoleRow)
                <div class="topnav-row2">
                        @if ($canSeeKaryawanMenu)
                            <a href="{{ route('my-staff') }}" wire:navigate class="nav-item nav-item-navy {{ request()->routeIs('my-staff*') ? 'active' : '' }}">
                                <i class="fa-solid fa-users text-xs"></i> MyStaff
                            </a>
                        @endif
                        {{-- ponytail: tombol Coaching disembunyikan sementara, aktifkan lagi ganti @if ($canManageCoaching) --}}
                        @if (false && $canManageCoaching)
                            <a href="{{ route('coaching') }}" wire:navigate class="nav-item nav-item-orange {{ request()->routeIs('coaching*') ? 'active' : '' }}">
                                <i class="fa-solid fa-clipboard-check text-xs"></i> Coaching
                            </a>
                        @endif
                        @if ($canAccessKeuangan)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-green {{ request()->routeIs(['keuangan.periode-gaji*', 'keuangan.payroll*', 'keuangan.komponen-gaji*', 'keuangan.penerima-komponen-gaji*', 'keuangan.insentif*', 'keuangan.mapping-komponen-gaji*', 'keuangan.koperasi*']) ? 'active' : '' }}">
                                    <i class="fa-solid fa-sack-dollar text-xs"></i> Penggajian <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu">
                                    {{-- Skeleton nav — halaman dashboard per modul belum dibangun, lihat
                                         planning/2026-07-20-portal-modul-kepegawaian-karyawan-dan-navigasi-dashboard.md §5.3 --}}
                                    <span class="dropdown-item" style="opacity:.5;cursor:not-allowed;" title="Segera hadir"><i class="fa-solid fa-chart-line"></i> Dashboard <span style="font-size:9px;">(segera)</span></span>
                                    <div style="height:1px;background:var(--border-soft);margin:6px 8px;"></div>
                                    <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Periode Gaji</a>
                                    <a href="{{ route('keuangan.payroll') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-money-check-dollar"></i> Payroll</a>
                                    <div style="height:1px;background:var(--border-soft);margin:6px 8px;"></div>
                                    <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Komponen Gaji</a>
                                    <a href="{{ route('keuangan.penerima-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-users-gear"></i> Penerima Komponen Gaji</a>
                                    <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-diagram-project"></i> Mapping Komponen Gaji</a>
                                    <div style="height:1px;background:var(--border-soft);margin:6px 8px;"></div>
                                    <a href="{{ route('keuangan.insentif') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hand-holding-dollar"></i> Insentif</a>
                                    <a href="{{ route('keuangan.koperasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-people-group"></i> Koperasi</a>
                                </div>
                            </div>
                        @endif
                        @if ($canAccessAbsensi)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-sky {{ request()->routeIs('presensi.*') ? 'active' : '' }}">
                                    <i class="fa-solid fa-calendar-check text-xs"></i> Absensi <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu">
                                    <span class="dropdown-item" style="opacity:.5;cursor:not-allowed;" title="Segera hadir"><i class="fa-solid fa-chart-line"></i> Dashboard <span style="font-size:9px;">(segera)</span></span>
                                    <div style="height:1px;background:var(--border-soft);margin:6px 8px;"></div>
                                    <a href="{{ route('presensi.jadwal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Jadwal</a>
                                    <a href="{{ route('presensi.hari-libur') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-flag"></i> Hari Libur & Cuti Bersama</a>
                                    <a href="{{ route('presensi.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan Absensi</a>
                                </div>
                            </div>
                        @endif
                        @if ($canAccessKepegawaian)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-pink {{ request()->routeIs(['kepegawaian.*', 'karyawan.show']) ? 'active' : '' }}">
                                    <i class="fa-solid fa-id-card-clip text-xs"></i> Kepegawaian <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu">
                                    <span class="dropdown-item" style="opacity:.5;cursor:not-allowed;" title="Segera hadir"><i class="fa-solid fa-chart-line"></i> Dashboard <span style="font-size:9px;">(segera)</span></span>
                                    <div style="height:1px;background:var(--border-soft);margin:6px 8px;"></div>
                                    <a href="{{ route('kepegawaian.karyawan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-users"></i> Karyawan</a>
                                    <a href="{{ route('kepegawaian.dokumen') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-folder-open"></i> Dokumen</a>
                                    @if (auth()->user()->can('ViewAny:UserTransition'))
                                        <a href="{{ route('kepegawaian.mutasi-karyawan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-right-left"></i> Perubahan Status</a>
                                    @endif
                                    <div style="height:1px;background:var(--border-soft);margin:6px 8px;"></div>
                                    <a href="{{ route('kepegawaian.rekap-izin') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-lines"></i> Rekap Izin</a>
                                    <a href="{{ route('kepegawaian.rekap-cuti') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-umbrella-beach"></i> Rekap Cuti</a>
                                    <a href="{{ route('kepegawaian.rekap-lembur') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-business-time"></i> Rekap Lembur</a>
                                </div>
                            </div>
                        @endif
                        @if ($canAccessLms)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-teal {{ request()->routeIs(['pelatihan.honorarium', 'pelatihan.kategori', 'pelatihan.kelola']) ? 'active' : '' }}">
                                    <i class="fa-solid fa-graduation-cap text-xs"></i> Learning Management System <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu">
                                    <a href="{{ route('pelatihan.kelola') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-book"></i> Pelatihan</a>
                                    <a href="{{ route('pelatihan.kategori') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-tags"></i> Kategori Pelatihan</a>
                                    <a href="{{ route('pelatihan.honorarium') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Honorarium</a>
                                </div>
                            </div>
                        @endif
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
