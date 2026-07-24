<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Kepegawaian\DocumentFormPage;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentFormPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsKepegawaian(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:UserDocuments', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Kepegawaian Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, 'Create:UserDocuments']);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(DocumentFormPage::class)->assertForbidden();
    }

    public function test_create_resignation_document_requires_file(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($hc);

        Livewire::test(DocumentFormPage::class)
            ->set('user_id', $employee->id)
            ->set('document_number', 'DOC/001')
            ->set('issued_date', now()->toDateString())
            ->call('save')
            ->assertHasErrors(['file']);
    }

    public function test_create_document_with_file_succeeds(): void
    {
        Storage::fake('public');

        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($hc);

        Livewire::test(DocumentFormPage::class)
            ->set('user_id', $employee->id)
            ->set('document_number', 'DOC/001')
            ->set('issued_date', now()->toDateString())
            ->set('file', UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'))
            ->call('save');

        $document = UserDocuments::where('document_number', 'DOC/001')->first();

        $this->assertNotNull($document);
        $this->assertEquals($employee->id, $document->user_id);
        Storage::disk('public')->assertExists($document->file_path);
    }

    public function test_retirement_details_are_stored(): void
    {
        Storage::fake('public');

        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($hc);

        Livewire::withQueryParams(['type' => 'retirement'])
            ->test(DocumentFormPage::class)
            ->assertSet('document_type', 'retirement')
            ->set('user_id', $employee->id)
            ->set('document_number', 'DOC/PENSIUN')
            ->set('issued_date', now()->toDateString())
            ->set('retirement_type', 'normal')
            ->set('reason', 'Usia pensiun')
            ->set('file', UploadedFile::fake()->create('pensiun.pdf', 100, 'application/pdf'))
            ->call('save');

        $document = UserDocuments::where('document_number', 'DOC/PENSIUN')->first();

        $this->assertNotNull($document);
        $this->assertEquals('normal', $document->details['retirement_type']);
        $this->assertEquals('Usia pensiun', $document->details['reason']);
    }

    public function test_edit_does_not_require_new_file(): void
    {
        Storage::fake('public');

        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $document = UserDocuments::create([
            'user_id' => $employee->id,
            'document_type' => 'resignation',
            'user_name' => $employee->name,
            'document_number' => 'DOC/LAMA',
            'issued_date' => now()->toDateString(),
            'file_path' => 'user-documents/' . $employee->id . '/lama.pdf',
        ]);

        $this->actingAs($hc);

        Livewire::test(DocumentFormPage::class, ['record' => $document])
            ->assertSet('document_number', 'DOC/LAMA')
            ->set('document_number', 'DOC/BARU')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('DOC/BARU', $document->fresh()->document_number);
    }

    public function test_edit_prefills_company_and_employee_even_when_employee_was_deactivated(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Dimas Permadi']);

        $document = UserDocuments::create([
            'user_id' => $employee->id,
            'document_type' => 'resignation',
            'user_name' => $employee->name,
            'document_number' => 'DOC/DIMAS',
            'issued_date' => now()->toDateString(),
            'file_path' => 'user-documents/' . $employee->id . '/dimas.pdf',
        ]);

        // Karyawan dinonaktifkan (soft-delete) SETELAH dokumennya dibuat — skenario yang
        // ditemukan user 2026-07-20: field Karyawan & Perusahaan kosong saat edit.
        $employee->delete();

        $this->actingAs($hc);

        Livewire::test(DocumentFormPage::class, ['record' => $document])
            ->assertSet('user_id', $employee->id)
            ->assertSet('company_id', $company->id)
            ->assertSee('Dimas Permadi');
    }

    public function test_stranger_from_other_company_cannot_edit(): void
    {
        $companyA = $this->makeCompany();
        $companyB = Companies::create(['code' => 'BBB', 'name' => 'Company B', 'location' => 'Test', 'is_active' => true]);

        $hc = $this->actingAsKepegawaian($companyA->id);
        $employeeB = User::factory()->create(['company_id' => $companyB->id]);

        $document = UserDocuments::create([
            'user_id' => $employeeB->id,
            'document_type' => 'resignation',
            'user_name' => $employeeB->name,
            'document_number' => 'DOC/B',
            'issued_date' => now()->toDateString(),
            'file_path' => 'user-documents/' . $employeeB->id . '/b.pdf',
        ]);

        $this->actingAs($hc);

        Livewire::test(DocumentFormPage::class, ['record' => $document])->assertForbidden();
    }
}
