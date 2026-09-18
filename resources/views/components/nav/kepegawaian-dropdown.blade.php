@php
    $canViewDashboard = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_DASHBOARD_KEPEGAWAIAN->value);
    $canViewKaryawan = auth()->user()->can('ViewAny:User');
    $canViewPengaturanKepegawaian = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN->value);
    $canViewAlurPersetujuan = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_ALUR_PERSETUJUAN->value);
    $canViewJabatan = auth()->user()->can('ViewAny:JobPositions');
    $canViewJobEvaluation = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_JOB_EVALUATION->value);
    $canViewDokumen = auth()->user()->can('ViewAny:UserDocuments');
    $canViewPerubahanStatus = auth()->user()->can('ViewAny:UserTransition');
    $canViewKontrak = auth()->user()->can('ViewAny:UserContract');
    $canViewOrientasiData = auth()->user()->can('ViewAny:UserOrientation');
    $canViewPermintaanTenagaKerja = auth()->user()->can('ViewAny:ManpowerRequests');
@endphp

<div class="dropdown">
    <a href="#" class="nav-item nav-item-pink {{ request()->routeIs(['kepegawaian.*', 'karyawan.show']) ? 'active' : '' }}">
        <i class="fa-solid fa-id-card-clip text-xs"></i> Kepegawaian <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-columns">
        <div class="dropdown-col">
            @if ($canViewDashboard)
                <a href="{{ route('kepegawaian.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            @endif
            @if ($canViewKaryawan)
                <a href="{{ route('kepegawaian.karyawan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-users"></i> Karyawan</a>
            @endif
            @if ($canViewAlurPersetujuan)
                <a href="{{ route('kepegawaian.alur-persetujuan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-bars-staggered"></i> Alur Persetujuan</a>
            @endif
            @if ($canViewPengaturanKepegawaian)
                <a href="{{ route('kepegawaian.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan Kepegawaian</a>
            @endif
        </div>
        @if ($canViewJabatan || $canViewJobEvaluation || $canViewDokumen || $canViewPermintaanTenagaKerja)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Jabatan</div>
            @if ($canViewJabatan)
                <a href="{{ route('kepegawaian.jabatan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-id-card-clip"></i> Jabatan</a>
            @endif
            @if ($canViewJobEvaluation)
                <a href="{{ route('kepegawaian.job-evaluation') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-table-list"></i> Job Evaluation</a>
            @endif
            @if ($canViewPermintaanTenagaKerja)
                <a href="{{ route('kepegawaian.permintaan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-right-left"></i> Permintaan Tenaga Kerja</a>
            @endif
            @if ($canViewDokumen)
                <a href="{{ route('kepegawaian.dokumen') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-folder-open"></i> Dokumen</a>
            @endif
        </div>
        @endif
        {{-- Orientasi + Perpanjangan Kontrak (alur pengajuan) ada di dropdown Sikartap. Di sini
             Status Karyawan = data kontrak berjalan + Perubahan Status generik (promosi/demosi/
             mutasi/PHK). Kontrak Karyawan dipindah ke sini 2026-09-07 - fokusnya data, bukan alur. --}}
        @if ($canViewPerubahanStatus || $canViewKontrak || $canViewOrientasiData)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Status Karyawan</div>
            @if ($canViewOrientasiData)
                <a href="{{ route('kepegawaian.orientasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user-graduate"></i> Orientasi Karyawan</a>
            @endif
            @if ($canViewKontrak)
                <a href="{{ route('kepegawaian.kontrak') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-contract"></i> Kontrak Karyawan</a>
            @endif
            @if ($canViewPerubahanStatus)
                <a href="{{ route('kepegawaian.mutasi-karyawan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-right-left"></i> Perubahan Status</a>
            @endif
        </div>
        @endif
    </div>
</div>
