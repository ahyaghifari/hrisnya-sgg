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
            <button type="button" onclick="togglePwaTugasSheet()" class="pwa-bottom-nav-item {{ request()->routeIs(['tugas*', 'pelatihan*']) ? 'active' : '' }}" style="border:none;background:none;padding:0;">
                <i class="fa-solid fa-list-check"></i> Penugasan
            </button>
        @endif

        <div class="pwa-bottom-nav-center">
            <button type="button" class="pwa-bottom-nav-center-btn" onclick="togglePwaMenuSheet()" aria-label="Menu">
                <i class="fa-solid fa-grip"></i>
            </button>
            <span>Menu</span>
        </div>

        @if ($canSubmitAttendance && $canAccessPengajuanAbsensiSaya)
            <a href="{{ route('my-attendance-requests') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('my-attendance-requests*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines"></i> Absensi
            </a>
        @else
            <a href="{{ route('organization-structure') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('organization-structure') ? 'active' : '' }}">
                <i class="fa-solid fa-sitemap"></i> Struktur
            </a>
        @endif

        <a href="{{ route('akun') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('akun') ? 'active' : '' }}">
            @if ($navPhotoUrl)
                <img src="{{ $navPhotoUrl }}" alt="" style="width:18px;height:18px;border-radius:50%;object-fit:cover;">
            @else
                <span style="width:18px;height:18px;border-radius:50%;background:var(--border-soft);color:var(--text-heading);font-size:8px;font-weight:700;display:flex;align-items:center;justify-content:center;">{{ $navInitials }}</span>
            @endif
            Akun
        </a>
    </div>
</nav>

{{-- Sheet "Penugasan" — popup berisi menu yang sifatnya penugasan ke karyawan (Kanban, Pelatihan). --}}
<div id="pwa-tugas-sheet-backdrop" class="pwa-menu-sheet-backdrop" onclick="togglePwaTugasSheet()"></div>
<div id="pwa-tugas-sheet" class="pwa-menu-sheet">
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
    <div class="pwa-menu-sheet-handle"></div>

    <div class="pwa-menu-grid">
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

        @if ($canAccessLms)
            <x-pwa-nav.lms-menu />
        @endif

        @if ($canSeeKaryawanMenu)
            <x-pwa-nav.coaching-counseling-menu />
        @endif

        @if ($canAccessPenilaian)
            <x-pwa-nav.penilaian-menu />
        @endif

        @if ($canSubmitAttendance)
            <a href="{{ route('organization-structure') }}" wire:navigate class="pwa-menu-grid-item">
                <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-sitemap"></i></span>
                <span class="pwa-menu-grid-label">Struktur Organisasi</span>
            </a>
        @endif

        @if ($canAccessHakAkses)
            <span class="pwa-menu-grid-divider">Manajemen Pengguna</span>
            <a href="{{ route('hak-akses') }}" wire:navigate class="pwa-menu-grid-item">
                <span class="pwa-menu-grid-icon sky"><i class="fa-solid fa-shield-halved"></i></span>
                <span class="pwa-menu-grid-label">Hak Akses</span>
            </a>
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
</script>
