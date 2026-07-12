<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    // Subject-subject Shield ("Verb:Subject") ini punya Resource yang sudah dihapus
    // dari codebase (modul doctor payroll lama, dsb) — permission-nya jadi tampil
    // sebagai section "ngambang" (ungrouped) di halaman Role karena RoleForm sudah
    // ngambil grup dari Resource yang benar-benar terdaftar, bukan tebak-tebakan.
    private const STALE_SUBJECTS = [
        'DoctorFeeSubmission',
        'DoctorPayrollPeriod',
        'MasterSalaryComponents',
        'MasterDoctorService',
        'ProgramIncentive',
    ];

    public function up(): void
    {
        Permission::query()
            ->where(function ($query) {
                foreach (self::STALE_SUBJECTS as $subject) {
                    $query->orWhere('name', 'like', "%:{$subject}");
                }
            })
            ->delete();
    }

    public function down(): void
    {
        // Data cleanup, bukan schema — kalau perlu balikin, generate ulang lewat Shield.
    }
};
