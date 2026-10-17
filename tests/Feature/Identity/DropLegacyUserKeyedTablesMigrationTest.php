<?php

namespace Tests\Feature\Identity;

use App\Models\Master\Companies;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/** Pembuangan tabel/kolom lama berbasis user_id: menolak kalau ada data yang belum punya padanan, selain itu membuang. */
class DropLegacyUserKeyedTablesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require base_path('database/migrations/2026_11_21_100000_drop_legacy_user_keyed_tables_and_columns.php');
    }

    private function oldKoperasiTable(): void
    {
        Schema::create('user_koperasi', fn (Blueprint $t) => $t->char('id', 26)->primary());
    }

    public function test_old_table_is_kept_when_a_row_has_no_copy(): void
    {
        $this->oldKoperasiTable();
        DB::table('user_koperasi')->insert(['id' => (string) Str::ulid()]);

        try {
            $this->migration()->up();
            $this->fail('Migrasi seharusnya menolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('user_koperasi', $e->getMessage());
        }

        $this->assertTrue(Schema::hasTable('user_koperasi'));
    }

    public function test_old_table_is_dropped_when_every_row_is_copied(): void
    {
        $this->oldKoperasiTable();
        $user = User::factory()->create();
        $id = (string) Str::ulid();
        DB::table('user_koperasi')->insert(['id' => $id]);
        DB::table('employee_koperasi')->insert([
            'id' => $id, 'employee_id' => $user->employee->id, 'amount' => 'cipher', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('user_koperasi'));
        $this->assertSame(1, DB::table('employee_koperasi')->count());
    }

    public function test_payroll_user_id_column_is_dropped_only_when_every_row_has_employee_id(): void
    {
        Schema::table('payrolls', fn (Blueprint $t) => $t->char('user_id', 26)->nullable());
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $id = (string) Str::ulid();
        DB::table('payrolls')->insert(['id' => $id, 'user_id' => null, 'employee_id' => null, 'payroll_period_id' => $period->id, 'created_at' => now(), 'updated_at' => now()]);

        try {
            $this->migration()->up();
            $this->fail('Migrasi seharusnya menolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('payrolls', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('payrolls', 'user_id'));

        DB::table('payrolls')->where('id', $id)->update(['employee_id' => User::factory()->create()->employee->id]);

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('payrolls', 'user_id'));
        $this->assertSame(1, DB::table('payrolls')->count());
    }
}
