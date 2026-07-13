@php
    $canSeeKaryawanMenu = auth()->user()->canAccessKaryawanMenu();
    $canSubmitAttendance = ! auth()->user()->canBrowseAllCompanies();
    $canAccessKeuangan = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value);
@endphp

<nav class="pwa-bottom-nav">
    <div class="pwa-bottom-nav-inner">
        <a href="{{ route('dashboard') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fa-solid fa-house"></i> Beranda
        </a>
        <button type="button" class="pwa-bottom-nav-item" style="border:none;background:none;padding:0;font-family:inherit;" onclick="toggleProduktivitasSheet()">
            <i class="fa-solid fa-layer-group"></i> MyWorkspace
        </button>

        <div class="pwa-bottom-nav-center">
            <button type="button" class="pwa-bottom-nav-center-btn" onclick="togglePwaMenuSheet()" aria-label="Menu">
                <i class="fa-solid fa-grip"></i>
            </button>
        </div>

        @if ($canSubmitAttendance)
            <a href="{{ route('my-attendance-requests') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('my-attendance-requests*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines"></i> Pengajuan
            </a>
        @else
            <a href="{{ route('organization-structure') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('organization-structure') ? 'active' : '' }}">
                <i class="fa-solid fa-sitemap"></i> Struktur
            </a>
        @endif

        <a href="{{ route('akun') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('akun') ? 'active' : '' }}">
            <i class="fa-solid fa-user"></i> Akun
        </a>
    </div>
</nav>

{{-- Sheet dibuka lewat tombol tengah — grid shortcut yang tergantung role (bisa lebih dari satu
     kalau user punya beberapa role sekaligus, mis. supervisor + finance). Ubah password/logout
     ada di halaman Akun (livewire/pwa/akun.blade.php), tidak diduplikasi di sini. --}}
<div id="pwa-menu-sheet-backdrop" class="pwa-menu-sheet-backdrop" onclick="togglePwaMenuSheet()"></div>
<div id="pwa-menu-sheet" class="pwa-menu-sheet">
    <div class="pwa-menu-sheet-handle"></div>

    <div class="pwa-menu-grid">
        @if ($canSeeKaryawanMenu)
            <a href="{{ route('my-staff') }}" wire:navigate class="pwa-menu-grid-item">
                <span class="pwa-menu-grid-icon navy"><i class="fa-solid fa-users"></i></span>
                <span class="pwa-menu-grid-label">MyStaff</span>
            </a>
        @endif

        @if ($canAccessKeuangan)
            <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="pwa-menu-grid-item">
                <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-sack-dollar"></i></span>
                <span class="pwa-menu-grid-label">Komponen Gaji</span>
            </a>
            <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="pwa-menu-grid-item">
                <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-diagram-project"></i></span>
                <span class="pwa-menu-grid-label">Mapping Komponen Gaji</span>
            </a>
            <a href="{{ route('keuangan.insentif') }}" wire:navigate class="pwa-menu-grid-item">
                <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                <span class="pwa-menu-grid-label">Insentif</span>
            </a>
        @endif

        @if ($canSubmitAttendance)
            <a href="{{ route('organization-structure') }}" wire:navigate class="pwa-menu-grid-item">
                <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-sitemap"></i></span>
                <span class="pwa-menu-grid-label">Struktur Organisasi</span>
            </a>
        @endif
    </div>
</div>

{{-- Sheet "MyWorkspace" — dibuka lewat tombol bekas "Presensi" di bottom nav (Presensi/data-diri
     tetap bisa diakses lewat kartu Data Diri di Dashboard, tidak hilang, cuma dipindah). Pelatihan
     sengaja diarahkan ke path statis (bukan route()) karena portal LMS belum dibangun — akan 404
     kalau diklik sampai halamannya ada, ini pilihan sadar bukan bug. --}}
<div id="produktivitas-sheet-backdrop" class="pwa-menu-sheet-backdrop" onclick="toggleProduktivitasSheet()"></div>
<div id="produktivitas-sheet" class="pwa-menu-sheet">
    <div class="pwa-menu-sheet-handle"></div>

    <div class="pwa-menu-grid">
        <a href="{{ route('kanban') }}" wire:navigate class="pwa-menu-grid-item">
            <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-table-columns"></i></span>
            <span class="pwa-menu-grid-label">Kanban</span>
        </a>
        <a href="/pelatihan" class="pwa-menu-grid-item">
            <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-graduation-cap"></i></span>
            <span class="pwa-menu-grid-label">Pelatihan</span>
        </a>
    </div>
</div>

<script>
    function togglePwaMenuSheet() {
        document.getElementById('pwa-menu-sheet').classList.toggle('open');
        document.getElementById('pwa-menu-sheet-backdrop').classList.toggle('open');
    }

    function toggleProduktivitasSheet() {
        document.getElementById('produktivitas-sheet').classList.toggle('open');
        document.getElementById('produktivitas-sheet-backdrop').classList.toggle('open');
    }
</script>
