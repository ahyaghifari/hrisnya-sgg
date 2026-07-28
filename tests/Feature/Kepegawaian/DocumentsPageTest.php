<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Kepegawaian\DocumentsPage;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentsPageTest extends TestCase
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

    private function makeDocument(User $user, string $type = 'resignation', array $overrides = []): UserDocuments
    {
        return UserDocuments::create(array_merge([
            'user_id' => $user->id,
            'document_type' => $type,
            'user_name' => $user->name,
            'document_number' => 'DOC/001/2026',
            'issued_date' => now()->toDateString(),
            'file_path' => 'user-documents/' . $user->id . '/doc.pdf',
        ], $overrides));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(DocumentsPage::class)->assertForbidden();
    }

    public function test_lists_only_documents_from_own_company(): void
    {
        $companyA = $this->makeCompany('AAA');
        $companyB = $this->makeCompany('BBB');

        $hc = $this->actingAsKepegawaian($companyA->id);

        $employeeA = User::factory()->create(['company_id' => $companyA->id, 'name' => 'Karyawan A']);
        $employeeB = User::factory()->create(['company_id' => $companyB->id, 'name' => 'Karyawan B']);

        $this->makeDocument($employeeA, 'resignation', ['document_number' => 'DOC-A']);
        $this->makeDocument($employeeB, 'resignation', ['document_number' => 'DOC-B']);

        $this->actingAs($hc);

        Livewire::test(DocumentsPage::class)
            ->set('documentType', 'resignation')
            ->assertSee('Karyawan A')
            ->assertDontSee('Karyawan B');
    }

    public function test_switching_type_filters_documents(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Uji']);

        $this->makeDocument($employee, 'resignation', ['document_number' => 'DOC-RESIGN']);
        $this->makeDocument($employee, 'retirement', ['document_number' => 'DOC-RETIRE']);

        $this->actingAs($hc);

        Livewire::test(DocumentsPage::class)
            ->assertSet('documentType', 'resignation')
            ->assertSee('DOC-RESIGN')
            ->assertDontSee('DOC-RETIRE')
            ->call('setType', 'retirement')
            ->assertSee('DOC-RETIRE')
            ->assertDontSee('DOC-RESIGN');
    }

    public function test_delete_soft_deletes_document_and_keeps_the_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('user-documents/dummy/doc.pdf', 'x');

        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $document = $this->makeDocument($employee, 'resignation', ['file_path' => 'user-documents/dummy/doc.pdf']);

        $this->actingAs($hc);

        Livewire::test(DocumentsPage::class)
            ->call('delete', $document->id);

        $this->assertNull(UserDocuments::find($document->id));
        $this->assertNotNull(UserDocuments::withTrashed()->find($document->id));
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('user-documents/dummy/doc.pdf');
    }

    public function test_default_status_shows_only_active_documents(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $active = $this->makeDocument($employee, 'resignation', ['document_number' => 'DOC-AKTIF']);
        $inactive = $this->makeDocument($employee, 'resignation', ['document_number' => 'DOC-NONAKTIF']);
        $inactive->delete();

        $this->actingAs($hc);

        Livewire::test(DocumentsPage::class)
            ->assertSet('status', 'aktif')
            ->assertSee('DOC-AKTIF')
            ->assertDontSee('DOC-NONAKTIF');
    }

    public function test_switching_to_inactive_status_shows_deleted_documents(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $active = $this->makeDocument($employee, 'resignation', ['document_number' => 'DOC-AKTIF']);
        $inactive = $this->makeDocument($employee, 'resignation', ['document_number' => 'DOC-NONAKTIF']);
        $inactive->delete();

        $this->actingAs($hc);

        Livewire::test(DocumentsPage::class)
            ->call('setStatus', 'tidak_aktif')
            ->assertSee('DOC-NONAKTIF')
            ->assertDontSee('DOC-AKTIF');
    }

    public function test_restore_reactivates_document(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $document = $this->makeDocument($employee, 'resignation');
        $document->delete();

        $this->actingAs($hc);

        Livewire::test(DocumentsPage::class)
            ->call('setStatus', 'tidak_aktif')
            ->call('restore', $document->id);

        $this->assertFalse($document->fresh()->trashed());
    }

    /**
     * Regresi: pagination di halaman ini pakai wire:navigate (full page-visit), yang bikin
     * komponen di-mount ULANG dari URL — tanpa #[Url] filter companyId hilang begitu pindah
     * halaman. Test GET langsung dengan query param companyId + page=2 (bukan Livewire::test)
     * biar simulasi mount dari URL yang sebenarnya kejadian saat wire:navigate.
     */
    public function test_company_filter_is_read_from_url_on_page_load(): void
    {
        $companyA = $this->makeCompany('CFA');
        $companyB = $this->makeCompany('CFB');

        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Kepegawaian Multi Company Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value]);
        $hc = User::factory()->create(['company_id' => $companyA->id]);
        $hc->assignRole($role);

        $employeeA = User::factory()->create(['company_id' => $companyA->id, 'name' => 'Karyawan A']);
        $employeeB = User::factory()->create(['company_id' => $companyB->id, 'name' => 'Karyawan B']);

        for ($i = 1; $i <= 15; $i++) {
            $this->makeDocument($employeeA, 'resignation', ['document_number' => "DOC-A-{$i}"]);
        }
        $this->makeDocument($employeeB, 'resignation', ['document_number' => 'DOC-B-1']);

        $response = $this->actingAs($hc)->get(route('kepegawaian.dokumen', ['companyId' => $companyA->id, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('DOC-A-11');
        $response->assertDontSee('DOC-B-1');
    }
}
