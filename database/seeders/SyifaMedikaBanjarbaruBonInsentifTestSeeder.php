<?php

namespace Database\Seeders;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Data pendukung buat tes import Bon & Insentif (mode import) di RSU Syifa Medika
 * Banjarbaru: aktifkan komponen, pastikan ada tipe karyawan, assign jadi penerima.
 * Idempotent (firstOrCreate/updateOrCreate) — aman dijalankan ulang.
 *
 * Sengaja TIDAK dipanggil dari DatabaseSeeder — dijalankan manual:
 *   php artisan db:seed --class=SyifaMedikaBanjarbaruBonInsentifTestSeeder
 */
class SyifaMedikaBanjarbaruBonInsentifTestSeeder extends Seeder
{
    public function run(): void
    {
        $company = Companies::where('name', 'like', '%Syifa Medika Banjarbaru%')->firstOrFail();

        $employeeType = EmployeeTypes::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Pegawai Tetap'],
        );

        foreach ([PayrollComponentEnum::BON, PayrollComponentEnum::INSENTIF] as $component) {
            CompanyPayrollComponent::updateOrCreate(
                ['company_id' => $company->id, 'component' => $component->value],
                ['is_active' => true],
            );

            PayrollComponentRecipient::firstOrCreate([
                'company_id' => $company->id,
                'component' => $component->value,
                'employee_type_id' => $employeeType->id,
            ]);
        }

        // Insentif harus mode "import" (bukan "master") buat company ini.
        Setting::updateOrCreate(
            ['company_id' => $company->id, 'key' => 'insentif_metode'],
            [
                'module' => 'payroll',
                'type' => 'string',
                'value' => 'import',
                'label' => 'Metode Insentif',
                'description' => '"Master" = input manual lewat tabel Insentif. "Import" = upload Excel per periode SETELAH payroll digenerate, dicocokkan lewat NIP.',
            ],
        );
    }
}
