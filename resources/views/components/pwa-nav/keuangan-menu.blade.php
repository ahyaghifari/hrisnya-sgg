@php
    // Sama seperti nav.blade.php desktop — gerbang spesifik dihitung di sini, bukan di parent.
    $canViewPayrollPeriod = auth()->user()->can('ViewAny:PayrollPeriod');
    $canViewPayroll = auth()->user()->can('ViewAny:Payroll');
    $canViewPajak = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PAJAK->value);
    $canViewDataRekening = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_DATA_REKENING->value);
    $canViewOmset = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_OMSET->value);
    $canViewInputJasaUnit = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_INPUT_JASA_UNIT->value);
    $canViewIncentive = auth()->user()->can('ViewAny:Incentive');
    $canViewKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_KOMPONEN_GAJI->value);
    $canViewPenerimaKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENERIMA_KOMPONEN_GAJI->value);
    $canViewMappingKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_MAPPING_KOMPONEN_GAJI->value);
    $canViewKoperasi = auth()->user()->can('ViewAny:UserKoperasi');
    $canViewBpjsDependent = auth()->user()->can('ViewAny:BpjsDependent');
    $canViewOverrideKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_OVERRIDE_KOMPONEN_GAJI->value);
    $canViewKenaikanGajiPokok = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_KENAIKAN_GAJI_POKOK->value);
    $canViewJasaUnitType = auth()->user()->can('ViewAny:JasaUnitType');
@endphp

<span class="pwa-menu-grid-divider">Sistem Keuangan</span>
<a href="{{ route('keuangan.dashboard') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon navy"><i class="fa-solid fa-chart-line"></i></span>
    <span class="pwa-menu-grid-label">Dashboard</span>
</a>
@if ($canViewPayrollPeriod)
    <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon navy"><i class="fa-solid fa-calendar-days"></i></span>
        <span class="pwa-menu-grid-label">Periode Gaji</span>
    </a>
@endif
@if ($canViewPayroll)
    <a href="{{ route('keuangan.payroll') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-money-check-dollar"></i></span>
        <span class="pwa-menu-grid-label">Payroll</span>
    </a>
@endif
@if ($canViewPajak)
    <a href="{{ route('keuangan.pajak') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-percent"></i></span>
        <span class="pwa-menu-grid-label">Pajak (%)</span>
    </a>
@endif
@if ($canViewDataRekening)
    <a href="{{ route('keuangan.data-rekening') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon indigo"><i class="fa-solid fa-building-columns"></i></span>
        <span class="pwa-menu-grid-label">Data Rekening</span>
    </a>
@endif
@if ($canViewOmset)
    <a href="{{ route('keuangan.omset') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-hand-holding-medical"></i></span>
        <span class="pwa-menu-grid-label">Omset</span>
    </a>
@endif
@if ($canViewKoperasi)
    <a href="{{ route('keuangan.koperasi') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-people-group"></i></span>
        <span class="pwa-menu-grid-label">Koperasi</span>
    </a>
@endif
@if ($canViewBpjsDependent)
    <a href="{{ route('keuangan.tanggungan-bpjs') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-house-medical"></i></span>
        <span class="pwa-menu-grid-label">Tanggungan BPJS</span>
    </a>
@endif
@if ($canViewOverrideKomponenGaji)
    <a href="{{ route('keuangan.override-komponen-gaji') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-sliders"></i></span>
        <span class="pwa-menu-grid-label">Khusus</span>
    </a>
@endif
@if ($canViewKomponenGaji)
    <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-sack-dollar"></i></span>
        <span class="pwa-menu-grid-label">Komponen Gaji</span>
    </a>
@endif
@if ($canViewPenerimaKomponenGaji)
    <a href="{{ route('keuangan.penerima-komponen-gaji') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-users-gear"></i></span>
        <span class="pwa-menu-grid-label">Penerima Komponen Gaji</span>
    </a>
@endif
@if ($canViewMappingKomponenGaji)
    <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-diagram-project"></i></span>
        <span class="pwa-menu-grid-label">Mapping Komponen Gaji</span>
    </a>
@endif
@if ($canViewKenaikanGajiPokok)
    <a href="{{ route('keuangan.kenaikan-gaji-pokok') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-arrow-trend-up"></i></span>
        <span class="pwa-menu-grid-label">Kenaikan Gaji Pokok</span>
    </a>
@endif
@if ($canViewJasaUnitType)
    <a href="{{ route('keuangan.jasa-unit-types') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-hospital-user"></i></span>
        <span class="pwa-menu-grid-label">Jasa Unit</span>
    </a>
@endif
@if ($canViewIncentive)
    <a href="{{ route('keuangan.insentif') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon green"><i class="fa-solid fa-hand-holding-dollar"></i></span>
        <span class="pwa-menu-grid-label">Insentif</span>
    </a>
@endif
@if ($canViewInputJasaUnit)
    <a href="{{ route('keuangan.jasa-unit') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-hospital-user"></i></span>
        <span class="pwa-menu-grid-label">Input Jasa Unit</span>
    </a>
@endif
