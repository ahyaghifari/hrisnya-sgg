@php
    $canViewPelatihan = auth()->user()->can('ViewAny:LmsCourses');
    $canViewKategori = auth()->user()->can('ViewAny:LmsCategories');
    $canViewPemateri = auth()->user()->can('ViewAny:Trainers');
    $canViewPengaturanLms = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_LMS->value);
    $canViewBankSoal = auth()->user()->can('ViewAny:LmsQuestionBanks');
    $canViewKuis = auth()->user()->can('ViewAny:LmsQuizzes');
    $canViewEvaluasi = auth()->user()->can('ViewAny:LmsFeedbackQuestion');
    $canViewBaseline = auth()->user()->can('ViewAny:LmsCourseBaselines');
    $canViewHonorarium = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_HONORARIUM_LMS->value);
    $canViewLaporan = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_LAPORAN_PELATIHAN->value);
@endphp
<div class="dropdown">
    <a href="#" class="nav-item nav-item-teal {{ request()->routeIs(['pelatihan.honorarium', 'pelatihan.kategori', 'pelatihan.kelola', 'pelatihan.pemateri', 'pelatihan.eksternal*', 'pelatihan.bank-soal', 'pelatihan.kuis', 'pelatihan.evaluasi', 'pelatihan.baseline', 'pelatihan.laporan', 'pelatihan.pengaturan']) ? 'active' : '' }}">
        <i class="fa-solid fa-graduation-cap text-xs"></i> Learning Management System <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-columns">
        @if ($canViewPelatihan || $canViewKategori || $canViewPemateri || $canViewPengaturanLms)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Pelatihan</div>
            @if ($canViewPelatihan)
                <a href="{{ route('pelatihan.kelola') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-book"></i> Pelatihan</a>
            @endif
            @if ($canViewKategori)
                <a href="{{ route('pelatihan.kategori') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-tags"></i> Kategori Pelatihan</a>
            @endif
            @if ($canViewPemateri)
                <a href="{{ route('pelatihan.pemateri') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chalkboard-user"></i> Pemateri</a>
            @endif
            @if ($canViewPengaturanLms)
                <a href="{{ route('pelatihan.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan LMS</a>
            @endif
        </div>
        @endif
        @if ($canViewBankSoal || $canViewKuis || $canViewEvaluasi || $canViewBaseline)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Kuis & Evaluasi</div>
            @if ($canViewBankSoal)
                <a href="{{ route('pelatihan.bank-soal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clipboard-list"></i> Bank Soal</a>
            @endif
            @if ($canViewKuis)
                <a href="{{ route('pelatihan.kuis') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-list-check"></i> Kuis</a>
            @endif
            @if ($canViewEvaluasi)
                <a href="{{ route('pelatihan.evaluasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-star"></i> Pertanyaan Evaluasi</a>
            @endif
            @if ($canViewBaseline)
                <a href="{{ route('pelatihan.baseline') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-bullseye"></i> Baseline Pelatihan</a>
            @endif
        </div>
        @endif
        @if ($canViewHonorarium || $canViewLaporan)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Honor & Laporan</div>
            @if ($canViewHonorarium)
                <a href="{{ route('pelatihan.honorarium') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Honorarium</a>
            @endif
            @if ($canViewLaporan)
                <a href="{{ route('pelatihan.laporan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-bar"></i> Laporan</a>
            @endif
        </div>
        @endif
    </div>
</div>
