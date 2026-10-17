<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee\Employee;
use App\Models\Master\Companies;
use App\Models\Payroll\JasaUnitType;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** jasa_unit_details -> payroll_jasa_unit_details: salinan data dengan id sama, tabel lama tidak disentuh. */
class PayrollJasaUnitDetailMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require base_path('database/migrations/2026_11_18_140000_create_payroll_jasa_unit_details_table.php');
    }

    private function oldRow(User $user, string $amount): string
    {
        $company = Companies::firstOrCreate(['code' => 'TST'], ['name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $type = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Ambulan', 'code' => 'AMB']);

        $id = (string) Str::ulid();
        DB::table('jasa_unit_details')->insert([
            'id' => $id, 'payroll_period_id' => $period->id, 'user_id' => $user->id, 'jasa_unit_type_id' => $type->id,
            'amount' => $amount, 'note' => 'catatan', 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_rows_are_copied_with_same_id_and_old_table_is_untouched(): void
    {
        $migration = $this->migration();
        $migration->down();

        $user = User::factory()->create();
        $id = $this->oldRow($user, 'ciphertext-apa-adanya');

        $migration->up();

        $copied = DB::table('payroll_jasa_unit_details')->where('id', $id)->first();
        $this->assertSame(Employee::where('user_id', $user->id)->value('id'), $copied->employee_id);
        $this->assertSame('ciphertext-apa-adanya', $copied->amount);
        $this->assertSame('catatan', $copied->note);
        $this->assertSame($user->id, $copied->created_by, 'aktor tetap menunjuk users');
        $this->assertSame(1, DB::table('jasa_unit_details')->count());
    }

    public function test_copy_refuses_when_a_row_has_no_employee(): void
    {
        $migration = $this->migration();
        $migration->down();

        $user = User::factory()->create();
        $this->oldRow($user, 'x');
        Employee::where('user_id', $user->id)->delete();

        $this->expectException(\RuntimeException::class);
        $migration->up();
    }
}
