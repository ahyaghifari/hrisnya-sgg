<div class="dropdown">
    <a href="#" class="nav-item nav-item-gold {{ request()->routeIs(['penilaian.template*', 'penilaian.jadwal*', 'penilaian.daftar*']) ? 'active' : '' }}">
        <i class="fa-solid fa-clipboard-check text-xs"></i> Penilaian Karyawan <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu">
        <a href="{{ route('penilaian.daftar') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-list-check"></i> Daftar Penilaian</a>
        <a href="{{ route('penilaian.jadwal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-check"></i> Jadwal Penilaian</a>
        <a href="{{ route('penilaian.template') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-lines"></i> Template Penilaian</a>
    </div>
</div>
