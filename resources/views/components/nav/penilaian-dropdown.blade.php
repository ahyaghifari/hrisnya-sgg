@php
    $canViewDaftar = auth()->user()->can('ViewAny:AssessmentUsers');
    $canViewJadwal = auth()->user()->can('ViewAny:Assessments');
    $canViewTemplate = auth()->user()->can('ViewAny:AssessmentTemplates');
@endphp
<div class="dropdown">
    <a href="#" class="nav-item nav-item-gold {{ request()->routeIs(['penilaian.template*', 'penilaian.jadwal*', 'penilaian.daftar*']) ? 'active' : '' }}">
        <i class="fa-solid fa-clipboard-check text-xs"></i> Penilaian Karyawan <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu">
        @if ($canViewDaftar)
            <a href="{{ route('penilaian.daftar') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-list-check"></i> Daftar Penilaian</a>
        @endif
        @if ($canViewJadwal)
            <a href="{{ route('penilaian.jadwal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-check"></i> Jadwal Penilaian</a>
        @endif
        @if ($canViewTemplate)
            <a href="{{ route('penilaian.template') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-lines"></i> Template Penilaian</a>
        @endif
    </div>
</div>
