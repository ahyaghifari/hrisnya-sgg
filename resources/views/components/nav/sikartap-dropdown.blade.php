@php
    // "Sikartap" = payung perjalanan status karyawan: Orientasi -> Kontrak -> Tetap.
    // Halaman-halamannya masih terpisah (route tidak diubah), disatukan di dropdown ini
    // (instruksi user 2026-09-04). 3 kolom: Karyawan Tetap | Kontrak | Orientasi - Kontrak &
    // Orientasi dipisah jadi kolom sendiri-sendiri (2026-09-05, dulu 1 kolom gabung).
    $canViewSikartap = auth()->user()->can('ViewAny:Sikartap');
    $canInputMcu = auth()->user()->canInputSikartapMcu()
        || auth()->user()->can(\App\Enum\CustomPermissionEnum::EDIT_INPUT_MCU->value);
    $canViewPengaturan = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value);
    $canViewKontrak = auth()->user()->can('ViewAny:UserContract');
    $canViewOrientasi = auth()->user()->can('ViewAny:OrientationSubmission');
    $canViewPerpanjanganKontrak = auth()->user()->can('ViewAny:ContractSubmission');
    $hasSikartap = $canViewSikartap || auth()->user()->can(\App\Enum\CustomPermissionEnum::SISTEM_SIKARTAP->value);
@endphp

<div class="dropdown">
    <a href="#" class="nav-item nav-item-violet {{ request()->routeIs(['sikartap.*', 'kepegawaian.orientasi', 'kepegawaian.kontrak', 'kepegawaian.perpanjangan-kontrak']) ? 'active' : '' }}">
        <i class="fa-solid fa-user-check text-xs"></i> Sikartap <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-columns">
        @if ($hasSikartap || $canInputMcu || $canViewPengaturan)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Karyawan Tetap</div>
            @if ($hasSikartap)
                <a href="{{ route('sikartap.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-list-check"></i> Pengajuan</a>
            @endif
            @if ($canViewSikartap)
                <a href="{{ route('sikartap.jadwal') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Jadwal Tes &amp; Wawancara</a>
            @endif
            @if ($hasSikartap)
                <a href="{{ route('sikartap.ide-inovasi.atasan.queue') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-lightbulb"></i> Ide Inovasi</a>
            @endif
            @if ($canInputMcu)
                <a href="{{ route('sikartap.mcu.queue') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-stethoscope"></i> Antrian MCU</a>
            @endif
            @if ($canViewPengaturan)
                <a href="{{ route('sikartap.pengaturan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan</a>
            @endif
        </div>
        @endif

        @if ($canViewKontrak || $canViewOrientasi || $canViewPerpanjanganKontrak)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Kontrak &amp; Orientasi</div>
            @if ($canViewKontrak)
                <a href="{{ route('kepegawaian.kontrak') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-contract"></i> Kontrak</a>
            @endif
            @if ($canViewPerpanjanganKontrak)
                <a href="{{ route('kepegawaian.perpanjangan-kontrak') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-signature"></i> Perpanjangan Kontrak</a>
            @endif
            @if ($canViewOrientasi)
                <a href="{{ route('kepegawaian.orientasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user-graduate"></i> Orientasi</a>
            @endif
        </div>
        @endif
    </div>
</div>
