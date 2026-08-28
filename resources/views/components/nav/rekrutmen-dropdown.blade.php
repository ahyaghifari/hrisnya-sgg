{{-- Nav group Rekrutmen — modul terpisah dari Kepegawaian (2026-08-28). Jadi dropdown
     beneran begitu halaman lain (Daftar Pelamar per lowongan diakses dari detail lowongan)
     bertambah. Sekarang cuma "Lowongan Kerja". --}}
<a href="{{ route('rekrutmen.lowongan') }}" wire:navigate class="nav-item nav-item-orange {{ request()->routeIs('rekrutmen.*') ? 'active' : '' }}">
    <i class="fa-solid fa-briefcase text-xs"></i> Rekrutmen
</a>
