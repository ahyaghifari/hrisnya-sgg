@php
    // Periode Gaji digerbangi ViewAny:PayrollPeriod (Shield) sendiri, bukan SISTEM_KEUANGAN -
    // dihitung di sini (bukan di nav.blade.php) karena ini gerbang spesifik satu sub-halaman,
    // bukan permission SISTEM_* umum.
    $canViewPayrollPeriod = auth()->user()->can('ViewAny:PayrollPeriod');
@endphp

<div class="dropdown">
    <a href="#" class="nav-item nav-item-green {{ request()->routeIs(['keuangan.dashboard*', 'keuangan.periode-gaji*', 'keuangan.payroll*', 'keuangan.komponen-gaji*', 'keuangan.penerima-komponen-gaji*', 'keuangan.insentif*', 'keuangan.mapping-komponen-gaji*', 'keuangan.koperasi*', 'keuangan.omset*', 'keuangan.jasa-unit*', 'keuangan.jasa-unit-types*', 'keuangan.tanggungan-bpjs*']) ? 'active' : '' }}">
        <i class="fa-solid fa-sack-dollar text-xs"></i> Penggajian <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-columns">
        <div class="dropdown-col">
            <div class="dropdown-col-label">Payroll</div>
            <a href="{{ route('keuangan.dashboard') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            @if ($canViewPayrollPeriod)
                <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Periode Gaji</a>
            @endif
            <a href="{{ route('keuangan.payroll') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-money-check-dollar"></i> Payroll</a>
            <a href="{{ route('keuangan.omset') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hand-holding-medical"></i> Omset</a>
            <a href="{{ route('keuangan.jasa-unit') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hospital-user"></i> Input Jasa Unit</a>
        </div>
        <div class="dropdown-col">
            <div class="dropdown-col-label">Tunjangan & Potongan</div>
            <a href="{{ route('keuangan.insentif') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hand-holding-dollar"></i> Insentif</a>
            <a href="{{ route('keuangan.jasa-unit-types') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-hospital-user"></i> Jasa Unit</a>
            <a href="{{ route('keuangan.koperasi') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-people-group"></i> Koperasi</a>
            <a href="{{ route('keuangan.tanggungan-bpjs') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-house-medical"></i> Tanggungan BPJS</a>
        </div>
        <div class="dropdown-col">
            <div class="dropdown-col-label">Pengaturan Komponen</div>
            <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Komponen Gaji</a>
            <a href="{{ route('keuangan.penerima-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-users-gear"></i> Penerima Komponen Gaji</a>
            <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-diagram-project"></i> Mapping Komponen Gaji</a>
        </div>
    </div>
</div>
