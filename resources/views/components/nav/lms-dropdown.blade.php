<div class="dropdown">
    <a href="#" class="nav-item nav-item-teal {{ request()->routeIs(['pelatihan.honorarium', 'pelatihan.kategori', 'pelatihan.kelola', 'pelatihan.pemateri', 'pelatihan.eksternal*', 'pelatihan.bank-soal', 'pelatihan.kuis', 'pelatihan.evaluasi', 'pelatihan.baseline', 'pelatihan.laporan', 'pelatihan.pengaturan']) ? 'active' : '' }}">
        <i class="fa-solid fa-graduation-cap text-xs"></i> Learning Management System <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-columns">
        <div class="dropdown-col">
            <div class="dropdown-col-label">Pelatihan</div>
            <a href="{{ route('pelatihan.kelola') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-book"></i> Pelatihan</a>
            <a href="{{ route('pelatihan.kategori') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-tags"></i> Kategori Pelatihan</a>
            <a href="{{ route('pelatihan.pemateri') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chalkboard-user"></i> Pemateri</a>
            {{-- TODO: route pelatihan.eksternal nonaktif sementara, class Livewire belum dibuat --}}
            <a href="{{ route('pelatihan.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan LMS</a>
        </div>
        <div class="dropdown-col">
            <div class="dropdown-col-label">Kuis & Evaluasi</div>
            <a href="{{ route('pelatihan.bank-soal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clipboard-list"></i> Bank Soal</a>
            <a href="{{ route('pelatihan.kuis') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-list-check"></i> Kuis</a>
            <a href="{{ route('pelatihan.evaluasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-star"></i> Pertanyaan Evaluasi</a>
            <a href="{{ route('pelatihan.baseline') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-bullseye"></i> Baseline Pelatihan</a>
        </div>
        <div class="dropdown-col">
            <div class="dropdown-col-label">Honor & Laporan</div>
            <a href="{{ route('pelatihan.honorarium') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Honorarium</a>
            <a href="{{ route('pelatihan.laporan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-bar"></i> Laporan</a>
        </div>
    </div>
</div>
