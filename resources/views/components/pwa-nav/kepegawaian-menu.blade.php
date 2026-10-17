@php
    $canViewDashboard = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_DASHBOARD_KEPEGAWAIAN->value);
    $canViewKaryawan = auth()->user()->can('ViewAny:User');
    $canViewPengaturanKepegawaian = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN->value);
    $canViewJabatan = auth()->user()->can('ViewAny:JobPositions');
    $canViewJobEvaluation = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_JOB_EVALUATION->value);
    $canViewPerubahanStatus = auth()->user()->can('ViewAny:UserTransition');
    $canViewKontrak = auth()->user()->can('ViewAny:EmployeeContract');
    $canViewOrientasiData = auth()->user()->can('ViewAny:EmployeeOrientation');
    $canViewPermintaanTenagaKerja = auth()->user()->can('ViewAny:ManpowerRequests');
    $canViewMerit = auth()->user()->can('ViewAny:MeritPointRule') || auth()->user()->can(\App\Enum\CustomPermissionEnum::BERI_MERIT_POINT->value);
@endphp

<span class="pwa-menu-grid-divider">Sistem Kepegawaian</span>
@if ($canViewDashboard)
<a href="{{ route('kepegawaian.dashboard') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-chart-line"></i></span>
    <span class="pwa-menu-grid-label">Dashboard</span>
</a>
@endif
@if ($canViewKaryawan)
<a href="{{ route('kepegawaian.karyawan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-users"></i></span>
    <span class="pwa-menu-grid-label">Karyawan</span>
</a>
@endif
@if ($canViewMerit)
<a href="{{ route('kepegawaian.merit-poin') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-star"></i></span>
    <span class="pwa-menu-grid-label">Merit Poin</span>
</a>
@endif
@if ($canViewPengaturanKepegawaian)
<a href="{{ route('kepegawaian.pengaturan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-gear"></i></span>
    <span class="pwa-menu-grid-label">Pengaturan Kepegawaian</span>
</a>
@endif
@if ($canViewJabatan)
<a href="{{ route('kepegawaian.jabatan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-id-card-clip"></i></span>
    <span class="pwa-menu-grid-label">Jabatan</span>
</a>
@endif
@if ($canViewJobEvaluation)
<a href="{{ route('kepegawaian.job-evaluation') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-table-list"></i></span>
    <span class="pwa-menu-grid-label">Job Evaluation</span>
</a>
@endif
{{-- Orientasi + Perpanjangan Kontrak (alur) di menu Sikartap. Kontrak Karyawan (data
     kontrak berjalan) dipindah ke sini 2026-09-07. --}}
@if ($canViewOrientasiData)
    <a href="{{ route('kepegawaian.orientasi') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-user-graduate"></i></span>
        <span class="pwa-menu-grid-label">Orientasi Karyawan</span>
    </a>
@endif
@if ($canViewKontrak)
    <a href="{{ route('kepegawaian.kontrak') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-file-contract"></i></span>
        <span class="pwa-menu-grid-label">Kontrak Karyawan</span>
    </a>
@endif
@if ($canViewPerubahanStatus)
    <a href="{{ route('kepegawaian.mutasi-karyawan') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-right-left"></i></span>
        <span class="pwa-menu-grid-label">Perubahan Status</span>
    </a>
@endif
@if ($canViewPermintaanTenagaKerja)
    <a href="{{ route('kepegawaian.permintaan') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-right-left"></i></span>
        <span class="pwa-menu-grid-label">Permintaan Tenaga Kerja</span>
    </a>
@endif
