@php
    // Dropdown dibubarkan 2026-08-25/26 (jadi 1 link ke dashboard), dibagi lagi 2026-08-28
    // (instruksi user). Halaman-halamannya masih terpisah, tinggal dikumpulkan di sini.
    $canViewSikartap = auth()->user()->can('ViewAny:Sikartap');
    $canInputMcu = auth()->user()->canInputSikartapMcu()
        || auth()->user()->can(\App\Enum\CustomPermissionEnum::EDIT_INPUT_MCU->value);
    $canViewPengaturan = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value);
@endphp

<div class="dropdown">
    <a href="#" class="nav-item nav-item-violet {{ request()->routeIs('sikartap.*') ? 'active' : '' }}">
        <i class="fa-solid fa-user-check text-xs"></i> Sikartap <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu">
        <a href="{{ route('sikartap.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-list-check"></i> Pengajuan</a>
        @if ($canViewSikartap)
            <a href="{{ route('sikartap.jadwal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Jadwal Tes &amp; Wawancara</a>
        @endif
        {{-- Ide Inovasi sebelum MCU: MCU di-gate ideInovasiApproved() (lihat AsesmenPerawatPage::mount).
             ponytail: tidak ada gate murah buat "apakah user ini atasan reviewer ide inovasi"
             (perlu query). Ditampilkan buat semua yang punya SISTEM_SIKARTAP; halaman-nya
             sendiri self-filter ke antrian milik user (kosong kalau bukan atasan). --}}
        <a href="{{ route('sikartap.ide-inovasi.atasan.queue') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-lightbulb"></i> Ide Inovasi</a>
        @if ($canInputMcu)
            <a href="{{ route('sikartap.mcu.queue') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-stethoscope"></i> Antrian MCU</a>
        @endif
        @if ($canViewPengaturan)
            <a href="{{ route('sikartap.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan</a>
        @endif
    </div>
</div>
