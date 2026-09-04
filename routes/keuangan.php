<?php

use App\Http\Controllers\PayrollManualLineTemplateController;
use App\Livewire\Keuangan\IncentivesPage;
use App\Livewire\Keuangan\KoperasiPage;
use App\Livewire\Keuangan\MappingKomponenGajiPage;
use App\Livewire\Keuangan\PajakPage;
use App\Livewire\Keuangan\PayrollDashboardPage;
use App\Livewire\Keuangan\PayrollDetailPage;
use App\Livewire\Keuangan\PayrollPeriodsPage;
use App\Livewire\Keuangan\PayrollsPage;
use App\Livewire\Keuangan\SalaryComponentsPage;
use Illuminate\Support\Facades\Route;

/**
 * Route modul Keuangan (Payroll & Komponen Gaji). Dipisah dari routes/web.php (2026-09-04) -
 * lihat komentar di routes/sikartap.php untuk konvensi split per-modul.
 */
Route::get('/keuangan/dashboard', PayrollDashboardPage::class)
    ->name('keuangan.dashboard');

Route::get('/keuangan/periode-gaji', PayrollPeriodsPage::class)
    ->name('keuangan.periode-gaji');

Route::get('/keuangan/payroll', PayrollsPage::class)
    ->name('keuangan.payroll');

Route::get('/keuangan/import-payroll-manual', \App\Livewire\Keuangan\ManualPayrollImportPage::class)
    ->name('keuangan.import-payroll-manual');

Route::get('/keuangan/pajak', PajakPage::class)
    ->name('keuangan.pajak');

Route::get('/keuangan/data-rekening', \App\Livewire\Keuangan\DataRekeningPage::class)
    ->name('keuangan.data-rekening');

Route::get('/keuangan/payroll/{payroll}', PayrollDetailPage::class)
    ->name('keuangan.payroll.show');

Route::get('/keuangan/payroll/{payroll}/preview', function (\App\Models\Payroll\Payroll $payroll) {
    abort_unless(\Illuminate\Support\Facades\Auth::user()->can('view', $payroll), 403);

    return app(\App\Services\Payroll\PayrollService::class)->preview($payroll);
})->name('keuangan.payroll.preview');

// Unduh ZIP slip gaji satu periode — GET biasa (stream BinaryFileResponse), BUKAN lewat
// Livewire yang membungkus file jadi base64 di JSON payload sampai memory habis.
Route::get('/keuangan/payroll/slip-zip/{period}', function (string $period) {
    abort_unless(\Illuminate\Support\Facades\Auth::user()->can('viewAny', \App\Models\Payroll\Payroll::class), 403);

    $query = \App\Models\Payroll\PayrollPeriod::query();
    if (\Illuminate\Support\Facades\Auth::user()->canFilterByCompany()) {
        $query->withoutGlobalScope('company');
    }
    $period = $query->with('company')->findOrFail($period);

    $service = app(\App\Services\Payroll\PayrollService::class);
    abort_unless(
        \Illuminate\Support\Facades\Storage::disk('local')->exists($service->slipZipRelPath($period->id)),
        404,
        'File ZIP slip belum siap.',
    );

    return $service->downloadStoredSlipZip($period);
})->name('keuangan.payroll.slip-zip');

Route::get('/keuangan/komponen-gaji', SalaryComponentsPage::class)
    ->name('keuangan.komponen-gaji');

Route::get('/keuangan/penerima-komponen-gaji', \App\Livewire\Keuangan\PayrollComponentRecipientsPage::class)
    ->name('keuangan.penerima-komponen-gaji');

Route::get('/keuangan/insentif', IncentivesPage::class)
    ->name('keuangan.insentif');

Route::get('/keuangan/tunjangan-eksekutif', \App\Livewire\Keuangan\ExecutiveAllowancesPage::class)
    ->name('keuangan.tunjangan-eksekutif');

Route::get('/keuangan/koperasi', KoperasiPage::class)
    ->name('keuangan.koperasi');

Route::get('/keuangan/tanggungan-bpjs', \App\Livewire\Keuangan\BpjsDependentsPage::class)
    ->name('keuangan.tanggungan-bpjs');

Route::get('/keuangan/override-komponen-gaji', \App\Livewire\Keuangan\PayrollComponentOverridesPage::class)
    ->name('keuangan.override-komponen-gaji');

Route::get('/keuangan/kenaikan-gaji-pokok', \App\Livewire\Keuangan\GajiPokokKenaikanOverridesPage::class)
    ->name('keuangan.kenaikan-gaji-pokok');

// Model & permission-nya Payroll (prefix /keuangan), tapi nav-nya digabung ke dropdown
// Learning Management System (lms-dropdown.blade.php), bukan dropdown Penggajian.
Route::get('/keuangan/gaji-pemateri', \App\Livewire\Keuangan\GajiPemateriPage::class)
    ->name('keuangan.gaji-pemateri');

Route::get('/keuangan/payroll/import-template/{type}', [PayrollManualLineTemplateController::class, 'downloadTemplate'])
    ->whereIn('type', ['bon', 'insentif'])
    ->name('keuangan.payroll.import-template');

Route::get('/keuangan/mapping-komponen-gaji', MappingKomponenGajiPage::class)
    ->name('keuangan.mapping-komponen-gaji');

Route::get('/keuangan/omset', \App\Livewire\Keuangan\OmsetPage::class)
    ->name('keuangan.omset');

Route::get('/keuangan/jasa-unit-types', \App\Livewire\Keuangan\JasaUnitTypesPage::class)
    ->name('keuangan.jasa-unit-types');

Route::get('/keuangan/jasa-unit', \App\Livewire\Keuangan\JasaUnitInputPage::class)
    ->name('keuangan.jasa-unit');

Route::get('/keuangan/jasa-unit/import-template', [\App\Http\Controllers\JasaUnitDetailTemplateController::class, 'downloadTemplate'])
    ->name('keuangan.jasa-unit.import-template');
