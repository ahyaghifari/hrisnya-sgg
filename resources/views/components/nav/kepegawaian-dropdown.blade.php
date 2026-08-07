<div class="dropdown">
    <a href="#" class="nav-item nav-item-pink {{ request()->routeIs(['kepegawaian.*', 'karyawan.show']) ? 'active' : '' }}">
        <i class="fa-solid fa-id-card-clip text-xs"></i> Kepegawaian <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-columns">
        <div class="dropdown-col">
            <a href="{{ route('kepegawaian.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="{{ route('kepegawaian.karyawan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-users"></i> Karyawan</a>
            @if (auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN->value))
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
