@php
    $canViewKaryawan = auth()->user()->can('ViewAny:User');
    $canViewPengaturanKepegawaian = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN->value);
    $canViewJabatan = auth()->user()->can('ViewAny:JobPositions');
    $canViewJobEvaluation = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_JOB_EVALUATION->value);   
    $canViewDokumen = auth()->user()->can('ViewAny:UserDocuments');
    $canViewPerubahanStatus = auth()->user()->can('ViewAny:UserTransition');
    $canViewPermintaanTenagaKerja = auth()->user()->can('ViewAny:ManpowerRequests');
@endphp

<span class="pwa-menu-grid-divider">Sistem Kepegawaian</span>
<a href="{{ route('kepegawaian.dashboard') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon navy"><i class="fa-solid fa-chart-line"></i></span>
    <span class="pwa-menu-grid-label">Dashboard</span>
</a>
@if ($canViewKaryawan)
<a href="{{ route('kepegawaian.karyawan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon violet"><i class="fa-solid fa-users"></i></span>
    <span class="pwa-menu-grid-label">Karyawan</span>
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
@if ($canViewDokumen)
<a href="{{ route('kepegawaian.dokumen') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-folder-open"></i></span>
    <span class="pwa-menu-grid-label">Dokumen</span>
</a>
@endif
{{-- Kontrak & Orientasi pindah ke menu Sikartap (2026-09-04). --}}
@if ($canViewPerubahanStatus)
    <a href="{{ route('kepegawaian.mutasi-karyawan') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-right-left"></i></span>
        <span class="pwa-menu-grid-label">Perubahan Status</span>
    </a>
@endif
@if ($canViewPermintaanTenagaKerja)
    <a href="{{ route('kepegawaian.permintaan') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-right-left"></i></span>
        <span class="pwa-menu-grid-label">Permintaan Tenaga Kerja</span>
    </a>
@endif
