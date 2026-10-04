<?php

namespace Tests\Feature\Identity;

use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Person\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** identity:purge-users = hapus permanen akun (demo) dengan pengaman bertingkat. */
class PurgeUsersTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    private function demo(string $nip): User
    {
        $this->company ??= Companies::create(['code' => 'PRG', 'name' => 'Company Purge', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $this->company->id, 'nip' => $nip]);
        $user->profile()->create(['no_nik' => '3201' . $nip]);

        return $user;
    }

    private function snapshot(string $createdBy): string
    {
        $flow = DB::table('m_approval_flows')->where('name', 'F')->value('id') ?? tap(Str::ulid()->toString(), fn ($id) => DB::table('m_approval_flows')->insert([
            'id' => $id, 'company_id' => $this->company->id, 'name' => 'F', 'module' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]));

        DB::table('approval_snapshots')->insert([
            'id' => $id = Str::ulid()->toString(), 'module_type' => 'x', 'module_id' => Str::ulid()->toString(),
            'flow_id' => $flow, 'flow_name' => 'F', 'steps_snapshot' => '[]', 'created_by' => $createdBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function activity(string $snapshotId, ?string $actedBy): string
    {
        DB::table('approval_activities')->insert([
            'id' => $id = Str::ulid()->toString(), 'snapshot_id' => $snapshotId, 'acted_by' => $actedBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_refuses_unknown_nip_and_protected_accounts(): void
    {
        $this->demo('D1');
        User::factory()->create(['id' => '01k8271ym5edqp4jm1kvwrdrr0', 'nip' => 'SA1']); // NON_KARYAWAN

        $this->artisan('identity:purge-users', ['nip' => ['D1', 'NOPE'], '--force' => true])->assertFailed();
        $this->artisan('identity:purge-users', ['nip' => ['SA1'], '--force' => true])->assertFailed();

        $this->assertSame(2, User::withoutGlobalScopes()->whereIn('nip', ['D1', 'SA1'])->count());
    }

    public function test_without_force_deletes_nothing(): void
    {
        $this->demo('D1');

        $this->artisan('identity:purge-users', ['nip' => ['D1']])->assertSuccessful();

        $this->assertSame(1, User::where('nip', 'D1')->count());
        $this->assertSame(1, Person::count());
    }

    public function test_force_deletes_user_and_copies_but_keeps_others(): void
    {
        $this->demo('D1');
        $this->demo('D2');
        $keep = $this->demo('KEEP');

        $this->artisan('identity:purge-users', ['nip' => ['D1', 'D2'], '--force' => true])->assertSuccessful();

        $this->assertSame(0, User::whereIn('nip', ['D1', 'D2'])->count());
        $this->assertSame([$keep->id], Person::pluck('user_id')->all(), 'Person yatim tidak boleh tertinggal.');
        $this->assertSame(1, DB::table('employees')->count());
    }

    public function test_blocker_with_rows_aborts_unless_explicitly_allowed(): void
    {
        $user = $this->demo('D1');
        $period = PayrollPeriod::create(['company_id' => $this->company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->artisan('identity:purge-users', ['nip' => ['D1'], '--force' => true])->assertFailed();
        $this->assertSame(1, User::where('nip', 'D1')->count());
        $this->assertSame(1, Payroll::count());

        $this->artisan('identity:purge-users', ['nip' => ['D1'], '--force' => true, '--also' => ['payrolls.user_id']])->assertSuccessful();
        $this->assertSame(0, User::where('nip', 'D1')->count());
        $this->assertSame(0, Payroll::count());
    }

    public function test_deletes_approvals_created_and_processed_only_by_demo_users(): void
    {
        $d1 = $this->demo('D1');
        $d2 = $this->demo('D2');
        $snap = $this->snapshot($d1->id);
        $this->activity($snap, $d2->id);

        $this->artisan('identity:purge-users', ['nip' => ['D1', 'D2'], '--force' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('approval_snapshots')->count());
        $this->assertSame(0, DB::table('approval_activities')->count());
    }

    public function test_demo_activity_on_someone_elses_request_needs_nullify_flag(): void
    {
        $demo = $this->demo('D1');
        $real = $this->demo('REAL');
        $snap = $this->snapshot($real->id);
        $activity = $this->activity($snap, $demo->id);

        $this->artisan('identity:purge-users', ['nip' => ['D1'], '--force' => true])->assertFailed();
        $this->assertSame(1, User::where('nip', 'D1')->count());

        $this->artisan('identity:purge-users', ['nip' => ['D1'], '--force' => true, '--nullify-approvals' => true])->assertSuccessful();

        $this->assertSame(0, User::where('nip', 'D1')->count());
        $this->assertSame(1, DB::table('approval_snapshots')->count(), 'Pengajuan milik orang lain tetap ada.');
        $this->assertNull(DB::table('approval_activities')->where('id', $activity)->value('acted_by'));
    }

    public function test_blocked_user_is_skipped_and_the_rest_are_still_deleted(): void
    {
        $withPayroll = $this->demo('D1');
        $clean = $this->demo('D2');
        $period = PayrollPeriod::create(['company_id' => $this->company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        Payroll::create(['user_id' => $withPayroll->id, 'payroll_period_id' => $period->id]);

        $this->artisan('identity:purge-users', ['nip' => ['D1', 'D2'], '--force' => true])
            ->expectsOutputToContain('DILEWATI')
            ->assertSuccessful();

        $this->assertSame(1, User::where('nip', 'D1')->count(), 'Yang bermasalah tidak disentuh.');
        $this->assertSame(0, User::where('id', $clean->id)->count());
        $this->assertSame(1, Payroll::count());
        $this->assertSame(1, Person::where('user_id', $withPayroll->id)->count(), 'Salinannya juga tetap ada.');
    }

    public function test_demo_user_with_request_processed_by_real_user_is_skipped_while_other_demo_is_deleted(): void
    {
        $shared = $this->demo('D1');
        $other = $this->demo('D2');
        $real = $this->demo('REAL');
        $this->activity($this->snapshot($shared->id), $real->id);

        $this->artisan('identity:purge-users', ['nip' => ['D1', 'D2'], '--force' => true])->assertSuccessful();

        $this->assertSame(1, User::where('nip', 'D1')->count());
        $this->assertSame(0, User::where('id', $other->id)->count());
        $this->assertSame(1, DB::table('approval_snapshots')->count());
    }

    public function test_skipping_one_user_also_skips_a_demo_user_who_acted_on_the_skipped_ones_request(): void
    {
        $skippedUser = $this->demo('D1');
        $dependent = $this->demo('D2');
        $real = $this->demo('REAL');

        $snap = $this->snapshot($skippedUser->id);
        $this->activity($snap, $real->id);      // membuat D1 dilewati
        $this->activity($snap, $dependent->id); // D2 memproses pengajuan D1 yang dipertahankan

        // Tanpa --nullify-approvals: D2 ikut dilewati (pengajuan itu tetap ada), tidak ada yang terhapus.
        $this->artisan('identity:purge-users', ['nip' => ['D1', 'D2'], '--force' => true])->assertFailed();
        $this->assertSame(2, User::whereIn('nip', ['D1', 'D2'])->count());

        // Dengan --nullify-approvals: D2 terhapus, namanya dikosongkan dari aktivitas; D1 tetap dilewati.
        $this->artisan('identity:purge-users', ['nip' => ['D1', 'D2'], '--force' => true, '--nullify-approvals' => true])->assertSuccessful();
        $this->assertSame(1, User::where('nip', 'D1')->count());
        $this->assertSame(0, User::where('id', $dependent->id)->count());
        $this->assertSame(2, DB::table('approval_activities')->where('snapshot_id', $snap)->count());
        $this->assertSame(1, DB::table('approval_activities')->where('snapshot_id', $snap)->whereNull('acted_by')->count());
    }

    public function test_request_created_by_demo_but_processed_by_a_real_user_is_not_deleted(): void
    {
        $demo = $this->demo('D1');
        $real = $this->demo('REAL');
        $snap = $this->snapshot($demo->id);
        $this->activity($snap, $real->id); // orang asli sudah memproses

        $this->artisan('identity:purge-users', ['nip' => ['D1'], '--force' => true])->assertFailed();

        $this->assertSame(1, User::where('nip', 'D1')->count());
        $this->assertSame(1, DB::table('approval_snapshots')->count());
    }
}
