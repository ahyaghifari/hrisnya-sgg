<?php

namespace Tests\Feature\Identity;

use App\Models\Employee\Employee;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * User::name/nip/company_id = employees (nama_lengkap, nip, company_id) dengan jatuh balik ke kolom
 * users selama masa transisi (config identity.legacy_user_columns true), penulisan lewat model
 * disimpan ke employees, dan relasi employee selalu ikut dimuat ($with) supaya tidak N+1.
 * Test yang memanggil skipUnlessLegacyColumns() hanya relevan selama kolom users masih ada.
 */
class UserFullNameTest extends TestCase
{
    use RefreshDatabase;

    private function skipUnlessLegacyColumns(): void
    {
        if (! User::legacyColumns()) {
            $this->markTestSkipped('Hanya relevan selama kolom users.name/nip/company_id masih ada (mode transisi).');
        }
    }

    public function test_schema_matches_the_legacy_columns_flag(): void
    {
        $this->assertSame(User::legacyColumns(), Schema::hasColumn('users', 'name'));
        $this->assertSame(User::legacyColumns(), Schema::hasColumn('users', 'nip'));
        $this->assertSame(User::legacyColumns(), Schema::hasColumn('users', 'company_id'));
    }

    public function test_writing_through_the_model_is_stored_in_employees(): void
    {
        $c1 = Companies::create(['code' => 'W1', 'name' => 'Company 1', 'location' => 'Test', 'is_active' => true]);
        $c2 = Companies::create(['code' => 'W2', 'name' => 'Company 2', 'location' => 'Test', 'is_active' => true]);

        $user = User::factory()->create(['name' => 'Budi', 'nip' => 'N-001', 'company_id' => $c1->id]);
        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['Budi', 'N-001', $c1->id], [$employee->nama_lengkap, $employee->nip, $employee->company_id]);

        $user->update(['name' => 'Budi Hartono', 'nip' => 'N-002', 'company_id' => $c2->id]);

        $employee->refresh();
        $this->assertSame(['Budi Hartono', 'N-002', $c2->id], [$employee->nama_lengkap, $employee->nip, $employee->company_id]);

        // Dibaca kembali (instance baru) lewat accessor.
        $fresh = User::find($user->id);
        $this->assertSame('Budi Hartono', $fresh->name);
        $this->assertSame('N-002', $fresh->nip);
        $this->assertSame($c2->id, $fresh->company_id);

        // Query berbasis employees menemukannya dengan nilai baru, bukan yang lama.
        $this->assertSame($user->id, User::whereNip('N-002')->value('id'));
        $this->assertNull(User::whereNip('N-001')->value('id'));
        $this->assertSame([$user->id], User::whereCompany($c2->id)->pluck('id')->all());
    }

    public function test_updating_one_field_keeps_the_others(): void
    {
        $company = Companies::create(['code' => 'W3', 'name' => 'Company 3', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['name' => 'Budi', 'nip' => 'N-010', 'company_id' => $company->id]);

        $user->update(['name' => 'Budi Baru']);

        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Budi Baru', $employee->nama_lengkap);
        $this->assertSame('N-010', $employee->nip);
        $this->assertSame($company->id, $employee->company_id);
    }

    public function test_nip_is_still_generated_for_a_new_user_without_nip(): void
    {
        $user = User::create(['name' => 'Baru', 'email' => 'baru@example.com', 'password' => 'secret123']);

        $this->assertNotEmpty($user->fresh()->nip);
        $this->assertSame($user->fresh()->nip, Employee::where('user_id', $user->id)->value('nip'));
    }

    public function test_full_name_is_an_alias_of_name(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);

        $this->assertSame('Budi Santoso', $user->fresh()->full_name);
        $this->assertSame('Budi Santoso', $user->fresh()->name);
    }

    public function test_name_comes_from_employee_when_it_differs_from_the_column(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);

        DB::table('employees')->where('user_id', $user->id)->update(['nama_lengkap' => 'Dr. Budi Santoso']);

        $this->assertSame('Dr. Budi Santoso', $user->fresh()->name);
    }

    public function test_name_falls_back_to_the_users_column_when_the_employee_copy_is_empty(): void
    {
        $this->skipUnlessLegacyColumns();

        $user = User::factory()->create(['name' => 'Budi Santoso']);
        DB::table('employees')->where('user_id', $user->id)->update(['nama_lengkap' => null]);

        $this->assertSame('Budi Santoso', $user->fresh()->full_name);
    }

    public function test_name_nip_and_company_id_are_read_from_employee_and_the_users_column_is_the_mirror(): void
    {
        $this->skipUnlessLegacyColumns();

        $company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Test', 'is_active' => true]);
        $other = Companies::create(['code' => 'C2', 'name' => 'Company 2', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['name' => 'Budi', 'nip' => 'NIP-A', 'company_id' => $company->id]);

        // Salinan employee berbeda dari kolom users (simulasi: employee yang dibaca).
        DB::table('employees')->where('user_id', $user->id)->update(['nama_lengkap' => 'Budi E', 'nip' => 'NIP-E', 'company_id' => $other->id]);
        $loaded = User::find($user->id);

        $this->assertSame('Budi E', $loaded->name);
        $this->assertSame('NIP-E', $loaded->nip);
        $this->assertSame($other->id, $loaded->company_id);

        // Nilai kolom users tetap bisa diambil (rollback aman), dan kolomnya ikut ditulis (cermin).
        $this->assertSame('Budi', $loaded->rawName());
        $this->assertSame('NIP-A', $loaded->rawNip());
        $this->assertSame($company->id, $loaded->rawCompanyId());
    }

    public function test_account_without_employee_row_uses_the_users_column(): void
    {
        $this->skipUnlessLegacyColumns();

        $admin = User::factory()->create(['name' => 'Super Admin']);
        DB::table('employees')->where('user_id', $admin->id)->delete();

        $loaded = User::withoutGlobalScopes()->find($admin->id);

        $this->assertNull($loaded->employee);
        $this->assertSame('Super Admin', $loaded->full_name);
    }

    public function test_listing_users_does_not_run_a_query_per_row(): void
    {
        Companies::create(['code' => 'N1', 'name' => 'Company', 'location' => 'Test', 'is_active' => true]);
        User::factory()->count(6)->create();

        DB::enableQueryLog();
        $names = User::query()->get()->map(fn (User $u) => $u->full_name);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(6, $names);
        $this->assertSame(2, $queries, 'users + employees (satu query untuk semua baris), bukan 1+N.');
    }

    public function test_user_loaded_through_a_relation_also_gets_employee_in_one_query(): void
    {
        User::factory()->count(4)->create();

        DB::enableQueryLog();
        // Pola `with('user')` di banyak halaman: Employee -> user (relasi) lalu baca nama.
        $names = Employee::with('user')->get()->map(fn ($e) => $e->user->full_name);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(4, $names);
        $this->assertLessThanOrEqual(4, $queries, 'Jumlah query konstan, tidak tumbuh per baris.');
    }

    public function test_initials(): void
    {
        $this->assertSame('BS', (new User(['name' => 'Budi Santoso']))->initials());
        $this->assertSame('BS', (new User(['name' => '  Budi   Santoso Wijaya ']))->initials(), 'Spasi ganda/ujung tidak menghasilkan huruf kosong.');
        $this->assertSame('B', (new User(['name' => 'Budi']))->initials());
        $this->assertSame('', (new User(['name' => '']))->initials());
        $this->assertSame('BSW', (new User(['name' => 'Budi Santoso Wijaya']))->initials(3));
    }
}
