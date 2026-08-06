@php
    $canSeeKaryawanMenu = auth()->user()->canAccessKaryawanMenu();
    $canSubmitAttendance = ! auth()->user()->canBrowseAllCompanies();
    $canAccessKeuangan = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value);
    $canAccessMaster = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_MASTER->value);
    $canAccessAbsensi = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_ABSENSI->value);
    $canAccessKepegawaian = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value);
    $canAccessLms = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_LMS->value);
    $canAccessPenilaian = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value);
    $canAccessHakAkses = auth()->user()->can(\App\Enum\CustomPermissionEnum::KELOLA_HAK_AKSES->value);
    // Gerbang baris 1 — 4 permission terpisah (Tugas/Pelatihan/Pengajuan Absensi/
    // Coaching&Counseling), dulu terbuka buat siapa saja yang login. Beranda tetap dikecualikan
    // (selalu bebas akses). TUGAS_SAYA baru (2026-08-07) - diassign otomatis ke semua role
    // existing lewat migration, sama pola CustomPermissionsSeeder::bootstrapSelfServicePermissions()
    // yang dipakai 3 permission lain di bawah ini.
    $canAccessTugasSaya = auth()->user()->can(\App\Enum\CustomPermissionEnum::TUGAS_SAYA->value);
    $canAccessPelatihanSaya = auth()->user()->can(\App\Enum\CustomPermissionEnum::PELATIHAN_SAYA->value);
    $canAccessPengajuanAbsensiSaya = auth()->user()->can(\App\Enum\CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value);
    $canAccessCoachingCounselingSaya = auth()->user()->can(\App\Enum\CustomPermissionEnum::COACHING_COUNSELING_SAYA->value);
    // Dropdown "Coaching & Counseling" (row 2) — SATU rule sama persis MyStaff/Riwayat Approval
    // ($canSeeKaryawanMenu, "punya bawahan"), bukan lagi gerbang permission Shield terpisah
    // (instruksi user 2026-07-26). Konsekuensinya: karyawan biasa (bukan atasan) yang ditunjuk
    // jadi konselor TIDAK lihat dropdown ini — jalur masuknya lewat tombol "Konsul ke Saya" di
    // halaman personal Counseling Saya (/counseling) sendiri, lihat CounselingPage.
    $canAccessCoachingCounseling = $canSeeKaryawanMenu;
    $showRoleRow = $canSeeKaryawanMenu || $canAccessKeuangan || $canAccessMaster || $canAccessAbsensi || $canAccessKepegawaian || $canAccessLms || $canAccessCoachingCounseling || $canAccessPenilaian || $canAccessHakAkses;
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
                @if ($canAccessCoachingCounselingSaya)
                    <div class="dropdown">
                        <a href="#" class="nav-item {{ request()->routeIs(['counseling*', 'coaching.saya*']) ? 'active' : '' }}">
                            <i class="fa-solid fa-comment-dots text-xs"></i> Pembinaan Saya <i class="fa-solid fa-chevron-down text-[10px]"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a href="{{ route('counseling') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-comment-dots"></i> Counseling Saya</a>
                            <a href="{{ route('coaching.saya') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user-check"></i> Coaching Saya</a>
                        </div>
                    </div>
                @endif
            </div>
            @if ($showRoleRow)
                <div class="topnav-row2">
                        @if ($canSeeKaryawanMenu)
                            <a href="{{ route('my-staff') }}" wire:navigate class="nav-item nav-item-navy {{ request()->routeIs('my-staff*') ? 'active' : '' }}">
                                <i class="fa-solid fa-users text-xs"></i> MyStaff
                            </a>
                        @endif
                        @if ($canAccessCoachingCounseling)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-indigo {{ request()->routeIs(['coaching', 'coaching.create', 'coaching.show', 'counseling.kelola*']) ? 'active' : '' }}">
                                    <i class="fa-solid fa-comment-dots text-xs"></i> Coaching & Counseling <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu">
                                    <a href="{{ route('coaching') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clipboard-check"></i> Coaching</a>
                                    <a href="{{ route('counseling.kelola') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-comments"></i> Counseling</a>
                                </div>
                            </div>
                        @endif
                        @if ($canSeeKaryawanMenu)
                            <a href="{{ route('riwayat-approval') }}" wire:navigate class="nav-item nav-item-orange {{ request()->routeIs('riwayat-approval*') ? 'active' : '' }}">
                                <i class="fa-solid fa-clock-rotate-left text-xs"></i> Riwayat Approval
                            </a>
                        @endif
                        @if ($canAccessKeuangan)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-green {{ request()->routeIs(['keuangan.dashboard*', 'keuangan.periode-gaji*', 'keuangan.payroll*', 'keuangan.komponen-gaji*', 'keuangan.penerima-komponen-gaji*', 'keuangan.insentif*', 'keuangan.mapping-komponen-gaji*', 'keuangan.koperasi*', 'keuangan.omset*', 'keuangan.jasa-unit*', 'keuangan.jasa-unit-types*', 'keuangan.tanggungan-bpjs*']) ? 'active' : '' }}">
                                    <i class="fa-solid fa-sack-dollar text-xs"></i> Penggajian <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-columns">
                                    <div class="dropdown-col">
                                        <div class="dropdown-col-label">Payroll</div>
                                        <a href="{{ route('keuangan.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                                        <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Periode Gaji</a>
                                        <a href="{{ route('keuangan.payroll') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-money-check-dollar"></i> Payroll</a>
                                        <a href="{{ route('keuangan.omset') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hand-holding-medical"></i> Omset</a>
                                        <a href="{{ route('keuangan.jasa-unit') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hospital-user"></i> Input Jasa Unit</a>
                                    </div>
                                    <div class="dropdown-col">
                                        <div class="dropdown-col-label">Tunjangan & Potongan</div>
                                        <a href="{{ route('keuangan.insentif') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hand-holding-dollar"></i> Insentif</a>
                                        <a href="{{ route('keuangan.jasa-unit-types') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hospital-user"></i> Jasa Unit</a>
                                        <a href="{{ route('keuangan.koperasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-people-group"></i> Koperasi</a>
                                        <a href="{{ route('keuangan.tanggungan-bpjs') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-house-medical"></i> Tanggungan BPJS</a>
                                    </div>
                                    <div class="dropdown-col">
                                        <div class="dropdown-col-label">Pengaturan Komponen</div>
                                        <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Komponen Gaji</a>
                                        <a href="{{ route('keuangan.penerima-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-users-gear"></i> Penerima Komponen Gaji</a>
                                        <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-diagram-project"></i> Mapping Komponen Gaji</a>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if ($canAccessAbsensi)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-sky {{ request()->routeIs('presensi.*') ? 'active' : '' }}">
                                    <i class="fa-solid fa-calendar-check text-xs"></i> Presensi & Absensi <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-columns">
                                    <div class="dropdown-col">
                                        <a href="{{ route('presensi.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                                        <a href="{{ route('presensi.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan Absensi</a>
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
                        @endif
                        @if ($canAccessKepegawaian)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-pink {{ request()->routeIs(['kepegawaian.*', 'karyawan.show']) ? 'active' : '' }}">
                                    <i class="fa-solid fa-id-card-clip text-xs"></i> Kepegawaian <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-columns">
                                    <div class="dropdown-col">
                                        <a href="{{ route('kepegawaian.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                                        <a href="{{ route('kepegawaian.karyawan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-users"></i> Karyawan</a>
                                        @if (auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value))
                                            <a href="{{ route('kepegawaian.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan Kepegawaian</a>
                                        @endif
                                    </div>
                                    <div class="dropdown-col">
                                        <div class="dropdown-col-label">Jabatan</div>
                                        <a href="{{ route('kepegawaian.jabatan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-id-card-clip"></i> Jabatan</a>
                                        <a href="{{ route('kepegawaian.job-evaluation') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-table-list"></i> Job Evaluation</a>
                                        <a href="{{ route('kepegawaian.dokumen') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-folder-open"></i> Dokumen</a>
                                        @if (auth()->user()->can('ViewAny:UserTransition'))
                                            <a href="{{ route('kepegawaian.mutasi-karyawan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-right-left"></i> Perubahan Status</a>
                                        @endif
                                    </div>
                                    @if (auth()->user()->can('ViewAny:JobOpenings') || auth()->user()->can('ViewAny:ManpowerRequests'))
                                        <div class="dropdown-col">
                                            <div class="dropdown-col-label">Rekrutmen</div>
                                            @if (auth()->user()->can('ViewAny:JobOpenings'))
                                                <a href="{{ route('kepegawaian.lowongan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-briefcase"></i> Lowongan Kerja</a>
                                            @endif
                                            @if (auth()->user()->can('ViewAny:ManpowerRequests'))
                                                <a href="{{ route('kepegawaian.permintaan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-right-left"></i> Permintaan Tenaga Kerja</a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if ($canAccessLms)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-teal {{ request()->routeIs(['pelatihan.honorarium', 'pelatihan.kategori', 'pelatihan.kelola', 'pelatihan.pemateri', 'pelatihan.eksternal*', 'pelatihan.bank-soal', 'pelatihan.kuis', 'pelatihan.evaluasi', 'pelatihan.baseline', 'pelatihan.laporan', 'pelatihan.pengaturan']) ? 'active' : '' }}">
                                    <i class="fa-solid fa-graduation-cap text-xs"></i> Learning Management System <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-columns">
                                    <div class="dropdown-col">
                                        <div class="dropdown-col-label">Pelatihan</div>
                                        <a href="{{ route('pelatihan.kelola') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-book"></i> Pelatihan</a>
                                        <a href="{{ route('pelatihan.kategori') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-tags"></i> Kategori Pelatihan</a>
                                        <a href="{{ route('pelatihan.pemateri') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chalkboard-user"></i> Pemateri</a>
                                        {{-- TODO: route pelatihan.eksternal nonaktif sementara, class Livewire belum dibuat --}}
                                        <a href="{{ route('pelatihan.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan LMS</a>
                                    </div>
                                    <div class="dropdown-col">
                                        <div class="dropdown-col-label">Kuis & Evaluasi</div>
                                        <a href="{{ route('pelatihan.bank-soal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clipboard-list"></i> Bank Soal</a>
                                        <a href="{{ route('pelatihan.kuis') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-list-check"></i> Kuis</a>
                                        <a href="{{ route('pelatihan.evaluasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-star"></i> Pertanyaan Evaluasi</a>
                                        <a href="{{ route('pelatihan.baseline') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-bullseye"></i> Baseline Pelatihan</a>
                                    </div>
                                    <div class="dropdown-col">
                                        <div class="dropdown-col-label">Honor & Laporan</div>
                                        <a href="{{ route('pelatihan.honorarium') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Honorarium</a>
                                        <a href="{{ route('pelatihan.laporan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-bar"></i> Laporan</a>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if ($canAccessPenilaian)
                            <div class="dropdown">
                                <a href="#" class="nav-item nav-item-gold {{ request()->routeIs(['penilaian.template*', 'penilaian.jadwal*', 'penilaian.daftar*']) ? 'active' : '' }}">
                                    <i class="fa-solid fa-clipboard-check text-xs"></i> Penilaian Karyawan <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </a>
                                <div class="dropdown-menu">
                                    <a href="{{ route('penilaian.daftar') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-list-check"></i> Daftar Penilaian</a>
                                    <a href="{{ route('penilaian.jadwal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-check"></i> Jadwal Penilaian</a>
                                    <a href="{{ route('penilaian.template') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-lines"></i> Template Penilaian</a>
                                </div>
                            </div>
                        @endif
                        @if ($canAccessHakAkses)
                            <a href="{{ route('hak-akses') }}" wire:navigate class="nav-item nav-item-sky {{ request()->routeIs('hak-akses*') ? 'active' : '' }}">
                                <i class="fa-solid fa-shield-halved text-xs"></i> Hak Akses
                            </a>
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
