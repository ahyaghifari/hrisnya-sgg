<?php

namespace Tests\Feature\Sikartap;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\Master\Companies\Pages\ManageCompanies;
use App\Models\Master\Companies;
use App\Models\Master\CompanyDirector;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\User;
use App\Services\CompanyDirectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Jabatan Direktur diinput lewat master Perusahaan (bukan halaman/resource sendiri). */
class CompanyDirectorViaCompanyResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']));

        return $user;
    }

    private function company(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
    }

    private function jobPosition(Companies $company): JobPositions
    {
        $dept = OrgStructure::create(['company_id' => $company->id, 'name' => 'Direksi', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);

        return JobPositions::create(['company_id' => $company->id, 'name' => 'Direktur Utama', 'planned_manpower' => 1, 'org_structure_id' => $dept->id]);
    }

    public function test_edit_company_sets_director_job_position(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $pos = $this->jobPosition($company);

        Livewire::actingAs($admin)
            ->test(ManageCompanies::class)
            ->callTableAction('edit', $company, data: [
                'name' => $company->name,
                'location' => $company->location,
                'is_active' => true,
                'companyDirector' => ['job_position_id' => $pos->id],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('company_directors', [
            'company_id' => $company->id,
            'job_position_id' => $pos->id,
        ]);
        $this->assertSame($pos->id, app(CompanyDirectorService::class)->jobPositionId($company->id));
    }

    public function test_column_shows_director_job_position(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $pos = $this->jobPosition($company);
        CompanyDirector::create(['company_id' => $company->id, 'job_position_id' => $pos->id]);

        Livewire::actingAs($admin)
            ->test(ManageCompanies::class)
            ->assertCanSeeTableRecords([$company])
            ->assertSee('Direktur Utama');
    }
}
