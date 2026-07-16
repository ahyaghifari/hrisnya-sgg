@php
    $canSeeKaryawanMenu = auth()->user()->canAccessKaryawanMenu();
    $canSubmitAttendance = ! auth()->user()->canBrowseAllCompanies();
    $canAccessKeuangan = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN->value);

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

        <button type="button" onclick="togglePwaTugasSheet()" class="pwa-bottom-nav-item {{ request()->routeIs(['kanban*', 'pelatihan*']) ? 'active' : '' }}" style="border:none;background:none;padding:0;">
            <i class="fa-solid fa-list-check"></i> Penugasan
        </button>

        <div class="pwa-bottom-nav-center">
            <button type="button" class="pwa-bottom-nav-center-btn" onclick="togglePwaMenuSheet()" aria-label="Menu">
                <i class="fa-solid fa-grip"></i>
            </button>
            <span>Menu</span>
        </div>

        @if ($canSubmitAttendance)
            <a href="{{ route('my-attendance-requests') }}" wire:navigate class="pwa-bottom-nav-item {{ request()->routeIs('my-attendance-requests*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines"></i> Pengajuan Absensi
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
        <a href="{{ route('kanban') }}" wire:navigate class="pwa-menu-grid-item">
            <span class="pwa-menu-grid-icon navy"><i class="fa-solid fa-table-columns"></i></span>
            <span class="pwa-menu-grid-label">Kanban</span>
        </a>

        <a href="{{ route('pelatihan') }}" wire:navigate class="pwa-menu-grid-item">
            <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-graduation-cap"></i></span>
            <span class="pwa-menu-grid-label">Pelatihan</span>
        </a>
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
