<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Assessment\CreateJadwalPenilaianPage;
use App\Livewire\Assessment\EditJadwalPenilaianPage;
use App\Livewire\Assessment\JadwalPenilaianPage;
use App\Models\Assessment\AssessmentTemplates;
use App\Models\Assessment\AssessmentTypes;
use App\Models\Assessment\Assessments;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JadwalPenilaianPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsPengelola(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Penilaian Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeTemplate(Companies $company): AssessmentTemplates
    {
        $type = AssessmentTypes::create(['name' => 'Tahunan']);

        return AssessmentTemplates::create(['company_id' => $company->id, 'type_id' => $type->id, 'name' => 'Template Uji']);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(JadwalPenilaianPage::class)->assertForbidden();
    }

    public function test_pengelola_can_create_jadwal(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);
        $template = $this->makeTemplate($company);

        $this->actingAs($admin);

        Livewire::test(CreateJadwalPenilaianPage::class)
            ->set('name', 'Penilaian Uji')
            ->set('template_id', $template->id)
            ->set('start_date', '2026-08-01')
            ->set('end_date', '2026-08-31')
            ->call('submit')
            ->assertHasNoErrors();

        $assessment = Assessments::where('name', 'Penilaian Uji')->first();

        $this->assertNotNull($assessment);
        $this->assertSame($company->id, $assessment->company_id);
        $this->assertSame('active', $assessment->status);
    }

    public function test_pengelola_can_edit_jadwal(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);
        $template = $this->makeTemplate($company);

        $assessment = Assessments::create([
            'company_id' => $company->id,
            'template_id' => $template->id,
            'name' => 'Penilaian Lama',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        Livewire::test(EditJadwalPenilaianPage::class, ['assessment' => $assessment])
            ->set('name', 'Penilaian Baru')
            ->set('status', 'inactive')
            ->call('submit')
            ->assertHasNoErrors();

        $assessment->refresh();

        $this->assertSame('Penilaian Baru', $assessment->name);
        $this->assertSame('inactive', $assessment->status);
    }
}
