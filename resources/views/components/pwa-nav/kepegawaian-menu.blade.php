<span class="pwa-menu-grid-divider">Sistem Kepegawaian</span>
<a href="{{ route('kepegawaian.dashboard') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon navy"><i class="fa-solid fa-chart-line"></i></span>
    <span class="pwa-menu-grid-label">Dashboard</span>
</a>
<a href="{{ route('kepegawaian.karyawan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-users"></i></span>
    <span class="pwa-menu-grid-label">Karyawan</span>
</a>
<a href="{{ route('kepegawaian.jabatan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-id-card-clip"></i></span>
    <span class="pwa-menu-grid-label">Jabatan</span>
</a>
<a href="{{ route('kepegawaian.dokumen') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-folder-open"></i></span>
    <span class="pwa-menu-grid-label">Dokumen</span>
</a>
@if (auth()->user()->can('ViewAny:UserTransition'))
    <a href="{{ route('kepegawaian.mutasi-karyawan') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-right-left"></i></span>
        <span class="pwa-menu-grid-label">Perubahan Status</span>
    </a>
@endif
@if (auth()->user()->can('ViewAny:JobOpenings'))
    <a href="{{ route('kepegawaian.lowongan') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-briefcase"></i></span>
        <span class="pwa-menu-grid-label">Lowongan Kerja</span>
    </a>
@endif
@if (auth()->user()->can('ViewAny:ManpowerRequests'))
    <a href="{{ route('kepegawaian.permintaan') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-right-left"></i></span>
        <span class="pwa-menu-grid-label">Permintaan Tenaga Kerja</span>
    </a>
@endif
