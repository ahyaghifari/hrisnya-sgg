@php
    // Sama seperti nav.blade.php desktop — gerbang spesifik dihitung di sini, bukan di parent.
    $canViewPayrollPeriod = auth()->user()->can('ViewAny:PayrollPeriod');
    $canViewPayroll = auth()->user()->can('ViewAny:Payroll');
    $canViewInputJasaUnit = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_INPUT_JASA_UNIT->value);
    $canViewIncentive = auth()->user()->can('ViewAny:Incentive');
    $canViewKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_KOMPONEN_GAJI->value);
    $canViewMappingKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_MAPPING_KOMPONEN_GAJI->value);
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
@if ($canViewKomponenGaji)
    <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-sack-dollar"></i></span>
        <span class="pwa-menu-grid-label">Komponen Gaji</span>
    </a>
@endif
@if ($canViewMappingKomponenGaji)
    <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon teal"><i class="fa-solid fa-diagram-project"></i></span>
        <span class="pwa-menu-grid-label">Mapping Komponen Gaji</span>
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
