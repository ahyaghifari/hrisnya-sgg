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
    $canViewEksternal = auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_LMS->value);
@endphp

<span class="pwa-menu-grid-divider">Learning Management System</span>
@if ($canViewPelatihan)
<a href="{{ route('pelatihan.kelola') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-book"></i></span>
    <span class="pwa-menu-grid-label">Pelatihan</span>
</a>
@endif
@if ($canViewKategori)
<a href="{{ route('pelatihan.kategori') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-tags"></i></span>
    <span class="pwa-menu-grid-label">Kategori Pelatihan</span>
</a>
@endif
@if ($canViewPemateri)
<a href="{{ route('pelatihan.pemateri') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-chalkboard-user"></i></span>
    <span class="pwa-menu-grid-label">Pemateri</span>
</a>
@endif
@if ($canViewEksternal)
<a href="{{ route('pelatihan.eksternal') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
    <span class="pwa-menu-grid-label">Pelatihan Eksternal</span>
</a>
@endif
@if ($canViewBankSoal)
<a href="{{ route('pelatihan.bank-soal') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-clipboard-list"></i></span>
    <span class="pwa-menu-grid-label">Bank Soal</span>
</a>
@endif
@if ($canViewKuis)
<a href="{{ route('pelatihan.kuis') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-list-check"></i></span>
    <span class="pwa-menu-grid-label">Kuis</span>
</a>
@endif
@if ($canViewHonorarium)
<a href="{{ route('pelatihan.honorarium') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-sack-dollar"></i></span>
    <span class="pwa-menu-grid-label">Honorarium</span>
</a>
@endif
@if ($canViewEvaluasi)
<a href="{{ route('pelatihan.evaluasi') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-star"></i></span>
    <span class="pwa-menu-grid-label">Pertanyaan Evaluasi</span>
</a>
@endif
@if ($canViewBaseline)
<a href="{{ route('pelatihan.baseline') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-bullseye"></i></span>
    <span class="pwa-menu-grid-label">Baseline Pelatihan</span>
</a>
@endif
<a href="{{ route('pelatihan.laporan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-chart-bar"></i></span>
    <span class="pwa-menu-grid-label">Laporan</span>
</a>
@if ($canViewPengaturanLms)
<a href="{{ route('pelatihan.pengaturan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-gear"></i></span>
    <span class="pwa-menu-grid-label">Pengaturan LMS</span>
</a>
@endif
