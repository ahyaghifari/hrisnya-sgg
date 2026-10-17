@php
    // Periode Gaji & Payroll digerbangi ViewAny:<Resource> (Shield) sendiri, bukan SISTEM_KEUANGAN -
    // dihitung di sini (bukan di nav.blade.php) karena ini gerbang spesifik satu sub-halaman,
    // bukan permission SISTEM_* umum.
    $canViewDashboard = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_DASHBOARD_KEUANGAN->value);
    $canViewPayrollPeriod = auth()->user()->can('ViewAny:PayrollPeriod');
    $canViewPayroll = auth()->user()->can('ViewAny:Payroll');
    $canViewPajak = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PAJAK->value);
    $canViewDataRekening = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_DATA_REKENING->value);
    // Omset - permission manual (LIHAT_OMSET/KELOLA_OMSET), bukan Shield ViewAny (model-nya
    // tidak punya Filament Resource/Policy sendiri).
    $canViewOmset = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_OMSET->value);
    $canViewInputJasaUnit = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_INPUT_JASA_UNIT->value);
    $canViewIncentive = auth()->user()->can('ViewAny:EmployeeIncentive');
    $canViewExecutiveAllowance = auth()->user()->can('ViewAny:EmployeeExecutiveAllowance');
    $canViewKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_KOMPONEN_GAJI->value);
    $canViewPenerimaKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PENERIMA_KOMPONEN_GAJI->value);
    $canViewMappingKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_MAPPING_KOMPONEN_GAJI->value);
    $canViewKoperasi = auth()->user()->can('ViewAny:EmployeeKoperasi');
    $canViewBpjsDependent = auth()->user()->can('ViewAny:EmployeeBpjsDependent');
    $canViewOverrideKomponenGaji = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_OVERRIDE_KOMPONEN_GAJI->value);
    $canViewKenaikanGajiPokok = auth()->user()->can(\App\Enum\CustomPermissionEnum::LIHAT_KENAIKAN_GAJI_POKOK->value);
    $canViewJasaUnitType = auth()->user()->can('ViewAny:JasaUnitType');
    $canImportPayrollManual = auth()->user()->can(\App\Enum\CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value);
@endphp

<div class="dropdown">
    <a href="#" class="nav-item nav-item-green {{ request()->routeIs(['keuangan.dashboard*', 'keuangan.periode-gaji*', 'keuangan.payroll*', 'keuangan.import-payroll-manual*', 'keuangan.pajak*', 'keuangan.data-rekening*', 'keuangan.komponen-gaji*', 'keuangan.penerima-komponen-gaji*', 'keuangan.insentif*', 'keuangan.mapping-komponen-gaji*', 'keuangan.koperasi*', 'keuangan.omset*', 'keuangan.jasa-unit*', 'keuangan.jasa-unit-types*', 'keuangan.tanggungan-bpjs*', 'keuangan.override-komponen-gaji*', 'keuangan.kenaikan-gaji-pokok*', 'keuangan.tunjangan-eksekutif*']) ? 'active' : '' }}">
        <i class="fa-solid fa-sack-dollar text-xs"></i> Penggajian <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-columns">
        <div class="dropdown-col">
            <div class="dropdown-col-label">Payroll</div>
            @if ($canViewDashboard)
                <a href="{{ route('keuangan.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            @endif
            @if ($canViewPayrollPeriod)
                <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Periode Gaji</a>
            @endif
            @if ($canViewPayroll)
                <a href="{{ route('keuangan.payroll') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-money-check-dollar"></i> Payroll</a>
            @endif
            @if ($canImportPayrollManual)
                <a href="{{ route('keuangan.import-payroll-manual') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-file-import"></i> Import Payroll Manual</a>
            @endif
            @if ($canViewPajak)
                <a href="{{ route('keuangan.pajak') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-percent"></i> Pajak (%)</a>
            @endif
            @if ($canViewDataRekening)
                <a href="{{ route('keuangan.data-rekening') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-building-columns"></i> Data Rekening</a>
            @endif
            @if ($canViewOmset)
                <a href="{{ route('keuangan.omset') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hand-holding-medical"></i> Omset</a>
            @endif
            @if ($canViewInputJasaUnit)
                <a href="{{ route('keuangan.jasa-unit') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hospital-user"></i> Input Jasa Unit</a>
            @endif
        </div>
        @if($canViewIncentive || $canViewExecutiveAllowance || $canViewJasaUnitType || $canViewKoperasi || $canViewBpjsDependent)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Tunjangan & Potongan</div>
            @if ($canViewIncentive)
                <a href="{{ route('keuangan.insentif') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hand-holding-dollar"></i> Insentif</a>
            @endif
            @if ($canViewExecutiveAllowance)
                <a href="{{ route('keuangan.tunjangan-eksekutif') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user-tie"></i> Eksekutif</a>
            @endif
            @if ($canViewJasaUnitType)
                <a href="{{ route('keuangan.jasa-unit-types') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hospital-user"></i> Jasa Unit</a>
            @endif
            @if ($canViewKoperasi)
                <a href="{{ route('keuangan.koperasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-people-group"></i> Koperasi</a>
            @endif
            @if ($canViewBpjsDependent)
                <a href="{{ route('keuangan.tanggungan-bpjs') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-house-medical"></i> Tanggungan BPJS</a>
            @endif
            @if ($canViewOverrideKomponenGaji)
                <a href="{{ route('keuangan.override-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sliders"></i> Khusus</a>
            @endif
        </div>
        @endif
        @if($canViewKomponenGaji || $canViewPenerimaKomponenGaji || $canViewMappingKomponenGaji || $canViewKenaikanGajiPokok)
        <div class="dropdown-col">
            <div class="dropdown-col-label">Pengaturan Komponen</div>
            @if ($canViewKomponenGaji)
                <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Komponen Gaji</a>
            @endif
            @if ($canViewPenerimaKomponenGaji)
                <a href="{{ route('keuangan.penerima-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-users-gear"></i> Penerima Komponen Gaji</a>
            @endif
            @if ($canViewMappingKomponenGaji)
                <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-diagram-project"></i> Mapping Komponen Gaji</a>
            @endif
            @if ($canViewKenaikanGajiPokok)
                <a href="{{ route('keuangan.kenaikan-gaji-pokok') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-arrow-trend-up"></i> Kenaikan Gaji Pokok</a>
            @endif
        </div>
        @endif
    </div>
</div>
