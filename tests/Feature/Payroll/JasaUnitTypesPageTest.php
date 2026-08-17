<?php

namespace Tests\Feature\Payroll;

use App\Enum\OrgStructurLevel;
use App\Livewire\Keuangan\JasaUnitTypesPage;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\Payroll\JasaUnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bug: divisi yang sudah dipilih untuk sebuah Jasa Unit tidak muncul tercentang lagi setelah
 * reload — TomSelect (data-tom-select) baca state "selected" dari atribut <option selected>
 * di HTML awal, tapi blade tidak pernah menandainya berdasar org_structure_ids yang tersimpan.
 */
class JasaUnitTypesPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsEditor(Companies $company): User
    {
        Permission::firstOrCreate(['name' => 'ViewAny:JasaUnitType', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:JasaUnitType', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Jasa Unit Type Editor Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:JasaUnitType', 'Update:JasaUnitType']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_saved_divisions_are_marked_selected_on_reload(): void
    {
        Permission::firstOrCreate(['name' => 'ViewAny:JasaUnitType', 'guard_name' => 'web']);

        $company = Companies::create(['code' => 'JUT', 'name' => 'RS Jasa Unit Test', 'location' => 'Test', 'is_active' => true]);
        $divisionA = OrgStructure::create(['company_id' => $company->id, 'name' => 'Rawat Inap', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        $divisionB = OrgStructure::create(['company_id' => $company->id, 'name' => 'Rawat Jalan', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);

        $jasaType = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan']);
        $jasaType->orgStructures()->sync([$divisionA->id]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $role = Role::firstOrCreate(['name' => 'Jasa Unit Type Test', 'guard_name' => 'web']);
        $role->givePermissionTo('ViewAny:JasaUnitType');
        $user->assignRole($role);

        $html = Livewire::actingAs($user)
            ->test(JasaUnitTypesPage::class)
            ->html();

        $this->assertStringContainsString(
            "value=\"{$divisionA->id}\" selected",
            $html,
            'Divisi yang sudah tersimpan harus ditandai selected di HTML awal (dibaca TomSelect saat init).'
        );
        $this->assertStringNotContainsString("value=\"{$divisionB->id}\" selected", $html);
    }

    public function test_duplicate_code_in_same_company_is_rejected(): void
    {
        $company = Companies::create(['code' => 'JUT2', 'name' => 'RS Jasa Unit Test 2', 'location' => 'Test', 'is_active' => true]);
        $editor = $this->actingAsEditor($company);
        JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan', 'code' => 'JU-001']);

        Livewire::actingAs($editor)
            ->test(JasaUnitTypesPage::class)
            ->call('addRow')
            ->set('rows.1.name', 'Jasa Infus')
            ->set('rows.1.code', 'JU-001')
            ->call('save')
            ->assertHasErrors(['rows.1.code']);

        $this->assertDatabaseMissing('jasa_unit_types', ['name' => 'Jasa Infus']);
    }

    public function test_same_code_allowed_across_different_companies(): void
    {
        $companyA = Companies::create(['code' => 'JUT3', 'name' => 'RS A', 'location' => 'Test', 'is_active' => true]);
        $companyB = Companies::create(['code' => 'JUT4', 'name' => 'RS B', 'location' => 'Test', 'is_active' => true]);
        JasaUnitType::create(['company_id' => $companyA->id, 'name' => 'Jasa Ambulan', 'code' => 'JU-001']);
        $editor = $this->actingAsEditor($companyB);

        Livewire::actingAs($editor)
            ->test(JasaUnitTypesPage::class)
            ->call('addRow')
            ->set('rows.0.name', 'Jasa Ambulan B')
            ->set('rows.0.code', 'JU-001')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jasa_unit_types', ['company_id' => $companyB->id, 'code' => 'JU-001']);
    }

    public function test_code_is_required(): void
    {
        $company = Companies::create(['code' => 'JUT5', 'name' => 'RS Jasa Unit Test 5', 'location' => 'Test', 'is_active' => true]);
        $editor = $this->actingAsEditor($company);

        Livewire::actingAs($editor)
            ->test(JasaUnitTypesPage::class)
            ->call('addRow')
            ->set('rows.0.name', 'Jasa Tanpa Kode')
            ->call('save')
            ->assertHasErrors(['rows.0.code']);

        $this->assertDatabaseMissing('jasa_unit_types', ['name' => 'Jasa Tanpa Kode']);
    }
}
