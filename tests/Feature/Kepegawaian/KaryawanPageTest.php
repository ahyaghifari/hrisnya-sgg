<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Kepegawaian\KaryawanPage;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KaryawanPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsKepegawaian(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Kepegawaian Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(string $code = 'TST'): Companies
    {
        return Companies::create(['code' => $code, 'name' => "Company {$code}", 'location' => 'Test', 'is_active' => true]);
    }

    private function actingAsMultiCompanyKepegawaian(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Kepegawaian Multi Company Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(KaryawanPage::class)->assertForbidden();
    }

    public function test_lists_only_employees_from_own_company(): void
    {
        $companyA = $this->makeCompany('AAA');
        $companyB = $this->makeCompany('BBB');

        $hc = $this->actingAsKepegawaian($companyA->id);

        $sameCompanyEmployee = User::factory()->create(['company_id' => $companyA->id, 'name' => 'Karyawan Sama Perusahaan']);
        $otherCompanyEmployee = User::factory()->create(['company_id' => $companyB->id, 'name' => 'Karyawan Beda Perusahaan']);

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->assertSee('Karyawan Sama Perusahaan')
            ->assertDontSee('Karyawan Beda Perusahaan');

        $this->assertNotEquals($sameCompanyEmployee->company_id, $otherCompanyEmployee->company_id);
    }

    public function test_search_filters_by_name(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        User::factory()->create(['company_id' => $company->id, 'name' => 'Citra Dewi']);

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Citra Dewi');
    }

    public function test_default_status_shows_only_active_employees(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $active = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Aktif']);
        $inactive = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Nonaktif']);
        $inactive->delete();

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->assertSet('status', 'aktif')
            ->assertSee('Karyawan Aktif')
            ->assertDontSee('Karyawan Nonaktif');
    }

    public function test_switching_to_inactive_status_shows_deactivated_employees(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $active = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Aktif']);
        $inactive = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Nonaktif']);
        $inactive->delete();

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->call('setStatus', 'tidak_aktif')
            ->assertSee('Karyawan Nonaktif')
            ->assertDontSee('Karyawan Aktif');
    }

    public function test_restore_reactivates_employee(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->delete();

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->call('setStatus', 'tidak_aktif')
            ->call('restore', $employee->id);

        $this->assertFalse($employee->fresh()->trashed());
    }

    /**
     * Regresi: KaryawanPage sebelumnya paginate() tanpa trait Livewire\WithPagination —
     * Livewire skip total path-resolver-nya buat komponen begitu (lihat
     * SupportPagination::skip()), jadi link paginasi dibuat pakai URL request AJAX
     * yang lagi berjalan (/livewire/update) alih-alih URL halaman asli, dan browser
     * berakhir GET ke /livewire/update?page=2 -> 405 Method Not Allowed. Ganti company/filter
     * pas masih di halaman >1 (tanpa reset ke halaman 1) adalah pemicu paling gampang kena.
     */
    public function test_changing_company_filter_resets_pagination_to_first_page(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        User::factory()->count(15)->create(['company_id' => $company->id]);

        $this->actingAs($hc);

        $component = Livewire::test(KaryawanPage::class)
            ->call('gotoPage', 2);

        $this->assertSame(2, $component->instance()->getPage());

        $component->set('companyId', $company->id);

        $this->assertSame(1, $component->instance()->getPage());
    }

    /**
     * Regresi: href paginasi sebelumnya dibangun dari $paginator->previousPageUrl()/nextPageUrl(),
     * yang di bawah Livewire pakai path resolver Livewire::originalPath() (tanpa leading slash).
     * Browser resolve href relatif tanpa slash depan itu terhadap "direktori" URL saat ini, bukan
     * root, jadi /kepegawaian/karyawan?page=2 berubah jadi /kepegawaian/kepegawaian/karyawan?page=2.
     * Fix: bangun href dari Livewire::originalUrl() (absolut) di pagination.blade.php.
     */
    public function test_pagination_links_do_not_duplicate_path_segment(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        User::factory()->count(15)->create(['company_id' => $company->id]);

        $response = $this->actingAs($hc)->get(route('kepegawaian.karyawan'));

        $response->assertOk();
        $response->assertSee('href="http://localhost/kepegawaian/karyawan?page=2"', false);
        $response->assertDontSee('/kepegawaian/kepegawaian/karyawan', false);
    }

    /**
     * Regresi bug dilaporkan user: pindah halaman paginasi lewat wire:navigate (lihat komentar
     * di partials/pagination.blade.php) itu full page-visit yang me-mount ULANG komponen dari
     * URL, bukan wire:click di komponen yang sama — filter company/department/search/status
     * yang bukan bagian dari query string ikut hilang begitu pindah halaman. Fix: properti
     * filter itu di-#[Url], jadi Livewire JS otomatis nulis nilainya ke query string browser
     * (pushState) begitu berubah — link paginasi berikutnya (dibangun dari path yang browser
     * kirim balik) otomatis ikut membawanya. Livewire::test() tidak menjalankan JS beneran,
     * jadi yang bisa+perlu dites di level PHP: begitu browser SUDAH ada di URL dengan
     * companyId+page (state yang seharusnya terjadi setelah pushState+klik paginasi), mount()
     * harus benar membaca & menerapkan filter itu — bukan mengembalikannya ke default.
     */
    public function test_company_and_department_filter_are_read_from_url_on_page_load(): void
    {
        $companyA = $this->makeCompany('CFA');
        $companyB = $this->makeCompany('CFB');
        $hc = $this->actingAsMultiCompanyKepegawaian();

        User::factory()->count(15)->create(['company_id' => $companyA->id, 'name' => 'Karyawan A']);
        User::factory()->count(5)->create(['company_id' => $companyB->id, 'name' => 'Karyawan B']);

        $response = $this->actingAs($hc)->get(route('kepegawaian.karyawan', ['companyId' => $companyA->id, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('Karyawan A');
        $response->assertDontSee('Karyawan B');
    }

    public function test_cannot_restore_employee_from_other_company(): void
    {
        $companyA = $this->makeCompany('AAA');
        $companyB = $this->makeCompany('BBB');

        $hc = $this->actingAsKepegawaian($companyA->id);

        $employee = User::factory()->create(['company_id' => $companyB->id]);
        $employee->delete();

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->call('setStatus', 'tidak_aktif')
            ->call('restore', $employee->id)
            ->assertForbidden();
    }
}
