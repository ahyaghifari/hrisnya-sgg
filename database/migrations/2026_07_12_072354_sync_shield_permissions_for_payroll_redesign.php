<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Regenerasi katalog permission & Policy Shield buat Resource yang berubah nama/baru
 * (SalaryComponents, SalaryComponentAssignments, Incentive, FeeSubmission, JobEvaluationCompany).
 * Pola sama persis 2026_07_08_230229_sync_filament_shield_permission_catalog.php — idempotent,
 * aman dijalankan ulang. Permission lama (mis. ViewAny:MasterSalaryComponents,
 * ViewAny:ProgramIncentive, ViewAny:DoctorFeeSubmission) otomatis jadi orphan, tidak dihapus di
 * sini (konsisten dengan keputusan: role di-assign ulang manual belakangan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('shield:generate', [
            '--panel' => 'admin',
            '--all' => true,
            '--option' => 'permissions',
            '--no-interaction' => true,
        ]);
    }

    public function down(): void
    {
        // Sengaja tidak direverse — permission yang sudah live tidak boleh ikut terhapus lewat rollback.
    }
};
