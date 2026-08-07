@php
    $canViewDaftar = auth()->user()->can('ViewAny:AssessmentUsers');
    $canViewJadwal = auth()->user()->can('ViewAny:Assessments');
    $canViewTemplate = auth()->user()->can('ViewAny:AssessmentTemplates');
@endphp

<span class="pwa-menu-grid-divider">Penilaian Karyawan</span>
@if ($canViewDaftar)
<a href="{{ route('penilaian.daftar') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-list-check"></i></span>
    <span class="pwa-menu-grid-label">Daftar Penilaian</span>
</a>
@endif
@if ($canViewJadwal)
<a href="{{ route('penilaian.jadwal') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-calendar-check"></i></span>
    <span class="pwa-menu-grid-label">Jadwal Penilaian</span>
</a>
@endif
@if ($canViewTemplate)
<a href="{{ route('penilaian.template') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-file-lines"></i></span>
    <span class="pwa-menu-grid-label">Template Penilaian</span>
</a>
@endif
