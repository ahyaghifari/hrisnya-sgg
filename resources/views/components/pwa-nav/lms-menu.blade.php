<span class="pwa-menu-grid-divider">Learning Management System</span>
<a href="{{ route('pelatihan.kelola') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-book"></i></span>
    <span class="pwa-menu-grid-label">Pelatihan</span>
</a>
<a href="{{ route('pelatihan.kategori') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-tags"></i></span>
    <span class="pwa-menu-grid-label">Kategori Pelatihan</span>
</a>
<a href="{{ route('pelatihan.pemateri') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-chalkboard-user"></i></span>
    <span class="pwa-menu-grid-label">Pemateri</span>
</a>
{{-- TODO: route pelatihan.eksternal nonaktif sementara, class Livewire belum dibuat --}}
<a href="{{ route('pelatihan.bank-soal') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-clipboard-list"></i></span>
    <span class="pwa-menu-grid-label">Bank Soal</span>
</a>
<a href="{{ route('pelatihan.kuis') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-list-check"></i></span>
    <span class="pwa-menu-grid-label">Kuis</span>
</a>
<a href="{{ route('pelatihan.honorarium') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-sack-dollar"></i></span>
    <span class="pwa-menu-grid-label">Honorarium</span>
</a>
<a href="{{ route('pelatihan.evaluasi') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-star"></i></span>
    <span class="pwa-menu-grid-label">Pertanyaan Evaluasi</span>
</a>
<a href="{{ route('pelatihan.baseline') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-bullseye"></i></span>
    <span class="pwa-menu-grid-label">Baseline Pelatihan</span>
</a>
<a href="{{ route('pelatihan.laporan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-chart-bar"></i></span>
    <span class="pwa-menu-grid-label">Laporan</span>
</a>
<a href="{{ route('pelatihan.pengaturan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-gear"></i></span>
    <span class="pwa-menu-grid-label">Pengaturan LMS</span>
</a>
