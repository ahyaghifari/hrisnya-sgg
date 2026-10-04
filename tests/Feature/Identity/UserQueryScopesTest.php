<?php

namespace Tests\Feature\Identity;

use App\Models\Employee\Employee;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Scope query User berbasis employees (pengganti kolom users.name/nip/company_id di dalam SQL) dan
 * login memakai NIP lewat employees.nip.
 */
class UserQueryScopesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $nip, ?Companies $company = null): User
    {
        return User::factory()->create(['name' => $name, 'nip' => $nip, 'company_id' => $company?->id]);
    }

    public function test_scopes_query_employees_not_the_users_columns(): void
    {
        $c1 = Companies::create(['code' => 'S1', 'name' => 'Company 1', 'location' => 'Test', 'is_active' => true]);
        $c2 = Companies::create(['code' => 'S2', 'name' => 'Company 2', 'location' => 'Test', 'is_active' => true]);
        $budi = $this->user('Budi', 'N-001', $c1);
        $siti = $this->user('Siti', 'N-002', $c2);

        // Ubah HANYA employees: kolom users sengaja dibiarkan beda, scope harus mengikuti employees.
        Employee::where('user_id', $budi->id)->update(['nama_lengkap' => 'Budi Hartono', 'nip' => 'E-001']);

        $this->assertSame($budi->id, User::whereNip('E-001')->value('id'));
        $this->assertNull(User::whereNip('N-001')->value('id'), 'Kolom users.nip tidak dicari lagi.');
        $this->assertSame([$budi->id], User::searchName('Hartono')->pluck('id')->all());
        $this->assertSame([], User::searchName('Budi')->where('users.id', '!=', $budi->id)->pluck('id')->all());
        $this->assertSame([$budi->id], User::searchNameOrNip('E-00')->pluck('id')->all());
        $this->assertSame([$siti->id], User::whereCompany($c2->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$budi->id, $siti->id], User::whereCompany([$c1->id, $c2->id])->pluck('id')->all());
    }

    public function test_where_company_null_means_users_without_a_company_like_the_old_where(): void
    {
        $company = Companies::create(['code' => 'S3', 'name' => 'Company 3', 'location' => 'Test', 'is_active' => true]);
        $withCompany = $this->user('Ada', 'N-1', $company);
        $without = $this->user('Tanpa', 'N-2');

        $this->assertSame([$without->id], User::whereCompany(null)->pluck('id')->all());
        $this->assertSame([$withCompany->id], User::whereCompany($company->id)->pluck('id')->all());
    }

    public function test_order_by_name_follows_employees(): void
    {
        $a = $this->user('Zed', 'N-1');
        $b = $this->user('Amir', 'N-2');
        Employee::where('user_id', $a->id)->update(['nama_lengkap' => 'Aaa']);
        Employee::where('user_id', $b->id)->update(['nama_lengkap' => 'Zzz']);

        $this->assertSame([$a->id, $b->id], User::orderByName()->pluck('id')->all());
        $this->assertSame([$b->id, $a->id], User::orderByName('desc')->pluck('id')->all());
        $this->assertSame(2, User::orderByNip()->count());
    }

    public function test_scopes_work_inside_where_has_on_relations(): void
    {
        $budi = $this->user('Budi', 'N-001');
        DB::table('user_profiles')->insert(['id' => (string) \Illuminate\Support\Str::ulid(), 'user_id' => $budi->id, 'created_at' => now(), 'updated_at' => now()]);

        $found = \App\Models\User\UserProfiles::whereHas('user', fn ($q) => $q->searchName('Budi'))->count();

        $this->assertSame(1, $found);
    }

    public function test_login_by_nip_resolves_the_user_through_employees(): void
    {
        $user = $this->user('Budi', 'N-001');

        $this->assertTrue(Auth::attempt(['nip' => 'N-001', 'password' => 'password']));
        $this->assertSame($user->id, Auth::id());
        Auth::logout();

        $this->assertFalse(Auth::attempt(['nip' => 'TIDAK-ADA', 'password' => 'password']));
        $this->assertFalse(Auth::attempt(['nip' => 'N-001', 'password' => 'salah']));
    }

    public function test_login_by_nip_still_works_for_accounts_hidden_by_the_non_karyawan_scope(): void
    {
        // Super Admin (NON_KARYAWAN) disembunyikan dari User::query() tapi harus tetap bisa login.
        $admin = User::factory()->create(['id' => '01k8271ym5edqp4jm1kvwrdrr0', 'name' => 'Super Admin', 'nip' => 'SA-001']);

        $this->assertTrue(Auth::attempt(['nip' => 'SA-001', 'password' => 'password']));
        $this->assertSame($admin->id, Auth::id());
    }

    public function test_login_by_email_is_unchanged(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $this->assertTrue(Auth::attempt(['email' => 'budi@example.com', 'password' => 'password']));
        $this->assertSame($user->id, Auth::id());
    }
}
