@props(['gates'])
@php
    // Gate SISTEM_* sudah dihitung sekali di layout.blade.php ($navGates), dioper ke sini lewat
    // prop $gates supaya tidak dicek dobel dengan <x-nav> yang selalu dirender bareng (cuma
    // disembunyikan CSS md:, lihat layout.blade.php). Gerbang spesifik/turunan
    // (ViewAny:PayrollPeriod, ViewAny:UserTransition, dll) tetap dihitung di dalam
    // <x-pwa-nav.xxx-menu> masing-masing, bukan di sini.
    extract($gates);

    $navPhotoPath = auth()->user()->profile?->profile_picture[0] ?? null;
    $navPhotoUrl = $navPhotoPath && is_string($navPhotoPath) ? \Illuminate\Support\Facades\Storage::url($navPhotoPath) : null;
    $navInitials = collect(explode(' ', auth()->user()->name))
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<nav class="pwa-bottom-nav">
    <div class="pwa-bottom-nav-inner">
        <a href="{{ route('dashboard') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fa-solid fa-house"></i> Beranda
        </a>

        @if ($canAccessTugasSaya || $canAccessPelatihanSaya)
            <button type="button" onclick="togglePwaTugasSheet()" class="pwa-bottom-nav-item {{ request()->routeIs(['tugas*', 'pelatihan*']) ? 'active' : '' }}" style="border:none;border-top:3px solid transparent;background:none;padding:0;">
                <i class="fa-solid fa-list-check"></i> Penugasan
            </button>
        @endif

        <button type="button" onclick="togglePwaMenuSheet()" class="pwa-bottom-nav-item" style="border:none;border-top:3px solid transparent;background:none;padding:0;" aria-label="Menu">
            <i class="fa-solid fa-grip"></i> Menu
        </button>

        @if ($canSubmitAttendance && $canAccessPengajuanAbsensiSaya)
            <a href="{{ route('my-attendance-requests') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('my-attendance-requests*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines"></i> Absensi
            </a>
        @endif

        <a href="{{ route('akun') }}" wire:navigate class="pwa-bottom-nav-item pwa-bottom-nav-akun {{ request()->routeIs('akun') ? 'active' : '' }}" aria-label="Akun">
            @if ($navPhotoUrl)
                <img src="{{ $navPhotoUrl }}" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">
            @else
                <span style="width:24px;height:24px;border-radius:50%;background:var(--border-soft);color:var(--text-heading);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">{{ $navInitials }}</span>
            @endif
            Akun
        </a>
    </div>
</nav>

{{-- Sheet "Penugasan" — popup berisi menu yang sifatnya penugasan ke karyawan (Kanban, Pelatihan). --}}
<div id="pwa-tugas-sheet-backdrop" class="pwa-menu-sheet-backdrop" onclick="togglePwaTugasSheet()"></div>
<div id="pwa-tugas-sheet" class="pwa-menu-sheet">
    <button type="button" class="pwa-menu-sheet-close" onclick="togglePwaTugasSheet()" aria-label="Tutup menu"><i class="fa-solid fa-xmark"></i></button>
    <div class="pwa-menu-sheet-handle"></div>

    {{-- 2 kolom saja (bukan 3 seperti sheet Menu) — cuma 2 item untuk sekarang. --}}
    <div class="pwa-menu-grid" style="grid-template-columns: repeat(2, 1fr);">
        @if ($canAccessTugasSaya)
            <a href="{{ route('tugas') }}" wire:navigate class="pwa-menu-grid-item">
                <span class="pwa-menu-grid-icon navy"><i class="fa-solid fa-table-columns"></i></span>
                <span class="pwa-menu-grid-label">Tugas</span>
            </a>
        @endif

        @if ($canAccessPelatihanSaya)
            <a href="{{ route('pelatihan') }}" wire:navigate class="pwa-menu-grid-item">
                <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-graduation-cap"></i></span>
                <span class="pwa-menu-grid-label">Pelatihan</span>
            </a>
        @endif
    </div>
</div>

{{-- Sheet dibuka lewat tombol tengah — grid shortcut yang tergantung role (bisa lebih dari satu
     kalau user punya beberapa role sekaligus, mis. supervisor + finance). Ubah password/logout
     ada di halaman Akun (livewire/pwa/akun.blade.php), tidak diduplikasi di sini. --}}
<div id="pwa-menu-sheet-backdrop" class="pwa-menu-sheet-backdrop" onclick="togglePwaMenuSheet()"></div>
<div id="pwa-menu-sheet" class="pwa-menu-sheet">
    <button type="button" class="pwa-menu-sheet-close" onclick="togglePwaMenuSheet()" aria-label="Tutup menu"><i class="fa-solid fa-xmark"></i></button>
    <div class="pwa-menu-sheet-handle"></div>

    <div class="pwa-menu-grid">
        @if ($canAccessPembinaanSaya || $canSubmitAttendance)
            <span class="pwa-menu-grid-divider">Menu Saya</span>
            @if ($canAccessPembinaanSaya)
                <a href="{{ route('coaching.saya') }}" wire:navigate class="pwa-menu-grid-item">
                    <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-user-check"></i></span>
                    <span class="pwa-menu-grid-label">Coaching Saya</span>
                </a>
                <a href="{{ route('counseling') }}" wire:navigate class="pwa-menu-grid-item">
                    <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-comment-dots"></i></span>
                    <span class="pwa-menu-grid-label">Counseling Saya</span>
                </a>
                <a href="{{ route('sp.saya') }}" wire:navigate class="pwa-menu-grid-item">
                    <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <span class="pwa-menu-grid-label">Surat Peringatan</span>
                </a>
            @endif
            {{-- Orientasi / Perpanjangan Kontrak / Karyawan Tetap dipindah ke Profil Saya →
                 kartu Status Kepegawaian (2026-09-08). --}}
        @endif

        @if ($canSeeKaryawanMenu)
            <x-pwa-nav.mystaff-item />
        @endif

        @if ($canAccessKeuangan)
            <x-pwa-nav.keuangan-menu />
        @endif

        @if ($canAccessAbsensi)
            <x-pwa-nav.absensi-menu />
        @endif

        @if ($canAccessKepegawaian)
            <x-pwa-nav.kepegawaian-menu />
        @endif

        @if ($canAccessRekrutmen)
            <x-pwa-nav.rekrutmen-menu />
        @endif

        @if ($canAccessSikartap)
            <x-pwa-nav.sikartap-menu />
        @endif

        @if ($canAccessMcu)
            <x-pwa-nav.mcu-menu />
        @endif

        @if ($canAccessLms)
            <x-pwa-nav.lms-menu />
        @endif

        @if ($canAccessCoachingCounseling)
            <x-pwa-nav.coaching-counseling-menu />
        @endif

        @if ($canAccessPenilaian)
            <x-pwa-nav.penilaian-menu />
        @endif

        @if ($canAccessHakAkses)
            <x-pwa-nav.manajemen-pengguna-menu />
        @endif
    </div>
</div>

<script>
    function togglePwaMenuSheet() {
        document.getElementById('pwa-menu-sheet').classList.toggle('open');
        document.getElementById('pwa-menu-sheet-backdrop').classList.toggle('open');
    }

    function togglePwaTugasSheet() {
        document.getElementById('pwa-tugas-sheet').classList.toggle('open');
        document.getElementById('pwa-tugas-sheet-backdrop').classList.toggle('open');
    }

    // Klik link di dalam sheet -> tutup dulu (animasi slide-down jalan), baru wire:navigate
    // pindah halaman. Tanpa ini sheet ke-reset mendadak saat halaman baru render.
    ['pwa-menu-sheet', 'pwa-tugas-sheet'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('click', function (e) {
            if (e.target.closest('a[href]')) {
                this.classList.remove('open');
                document.getElementById(id + '-backdrop')?.classList.remove('open');
            }
        });
    });
</script>
