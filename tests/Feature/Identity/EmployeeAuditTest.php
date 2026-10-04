<?php

namespace Tests\Feature\Identity;

use App\Models\AuditLog;
use App\Models\Employee\Employee;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit perubahan nama/NIP/perusahaan dicatat dari Employee (sumbernya), dengan kunci
 * `{user_id}:name|nip|company_id` yang sama dengan catatan lama User - tanpa baris ganda.
 */
class EmployeeAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        // AuditLog::record() hanya mencatat bila ada aktor yang login; tanpa ini test "tidak mencatat" lulus kosong.
        $this->actor = User::factory()->create();
        $this->actingAs($this->actor);
    }

    private function logs(User $user)
    {
        return AuditLog::where('module', 'data_karyawan')->where('key', 'like', $user->id . ':%')->orderBy('id')->get();
    }

    public function test_changing_name_nip_and_company_writes_exactly_one_row_each_with_the_old_keys(): void
    {
        $c1 = Companies::create(['code' => 'A1', 'name' => 'Company 1', 'location' => 'Test', 'is_active' => true]);
        $c2 = Companies::create(['code' => 'A2', 'name' => 'Company 2', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['name' => 'Budi', 'nip' => 'N-001', 'company_id' => $c1->id]);

        $user->update(['name' => 'Budi Hartono', 'nip' => 'N-002', 'company_id' => $c2->id]);

        $rows = $this->logs($user)->keyBy(fn ($r) => substr($r->key, strlen($user->id) + 1));

        $this->assertEqualsCanonicalizing(['name', 'nip', 'company_id'], $rows->keys()->all(), 'Satu baris per field, tidak ganda.');
        $this->assertSame(['Nama', 'Budi', 'Budi Hartono'], [$rows['name']->label, $rows['name']->old_value, $rows['name']->new_value]);
        $this->assertSame(['NIP', 'N-001', 'N-002'], [$rows['nip']->label, $rows['nip']->old_value, $rows['nip']->new_value]);
        $this->assertSame(['Perusahaan', $c1->id, $c2->id], [$rows['company_id']->label, $rows['company_id']->old_value, $rows['company_id']->new_value]);
        $this->assertSame($this->actor->id, $rows['name']->user_id, 'Aktor = yang sedang login, bukan pemilik data.');
        $this->assertSame($c2->id, $rows['name']->company_id);
    }

    public function test_creating_a_user_and_saving_without_change_write_nothing(): void
    {
        $user = User::factory()->create(['name' => 'Budi', 'nip' => 'N-010']);
        $this->assertCount(0, $this->logs($user), 'Pembuatan akun bukan histori perubahan.');

        $user->update(['name' => 'Budi', 'nip' => 'N-010']);
        $this->assertCount(0, $this->logs($user));
    }

    public function test_updating_only_one_field_logs_only_that_field(): void
    {
        $user = User::factory()->create(['name' => 'Budi', 'nip' => 'N-020']);

        $user->update(['nip' => 'N-021']);

        $this->assertSame(['nip'], $this->logs($user)->map(fn ($r) => substr($r->key, strlen($user->id) + 1))->all());
    }

    public function test_initial_fill_of_an_empty_employee_name_is_not_an_audited_change(): void
    {
        $user = User::factory()->create(['name' => 'Budi']);
        DB::table('employees')->where('user_id', $user->id)->update(['nama_lengkap' => null]);

        // Simulasi identity:backfill pertama setelah migrasi: nama_lengkap kosong -> terisi.
        Employee::where('user_id', $user->id)->firstOrFail()->update(['nama_lengkap' => 'Budi']);

        $this->assertCount(0, $this->logs($user));
    }

    public function test_user_model_no_longer_audits_the_moved_fields_but_still_audits_email(): void
    {
        $user = User::factory()->create(['email' => 'lama@example.com']);

        $user->update(['email' => 'baru@example.com']);

        $this->assertSame(['email'], $this->logs($user)->map(fn ($r) => substr($r->key, strlen($user->id) + 1))->all());
    }
}
