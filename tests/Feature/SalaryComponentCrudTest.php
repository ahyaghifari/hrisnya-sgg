<?php

namespace Tests\Feature;

use App\Filament\Resources\SalaryComponents\Pages\ListSalaryComponents;
use App\Models\Master\Companies;
use App\Models\Payroll\SalaryComponentAssignments;
use App\Models\SalaryComponent\SalaryComponents;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalaryComponentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_type_is_auto_derived_from_category_on_save(): void
    {
        $earning = SalaryComponents::create([
            'name' => 'Gaji Pokok',
            'category' => 'gaji_pokok',
            'calculation_type' => 'fixed',
            'is_active' => true,
        ]);

        $deduction = SalaryComponents::create([
            'name' => 'Cuti',
            'category' => 'cuti',
            'calculation_type' => 'fixed',
            'is_active' => true,
        ]);

        $this->assertSame('earning', $earning->type);
        $this->assertSame('deduction', $deduction->type);
    }

    public function test_list_page_renders(): void
    {
        $admin = $this->actingAsAdmin();
        SalaryComponents::create([
            'name' => 'Gaji Pokok',
            'category' => 'gaji_pokok',
            'calculation_type' => 'fixed',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/salary-components');

        $response->assertOk();
        $response->assertSee('Gaji Pokok');
    }

    public function test_can_create_salary_component(): void
    {
        $admin = $this->actingAsAdmin();

        Livewire::actingAs($admin)
            ->test(ListSalaryComponents::class)
            ->callAction('create', data: [
                'name' => 'Tunjangan Kesehatan',
                'category' => 'tunjangan',
                'calculation_type' => 'fixed',
                'default_value' => 250000,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('salary_components', [
            'name' => 'Tunjangan Kesehatan',
            'category' => 'tunjangan',
            'type' => 'earning',
        ]);
    }

    public function test_can_edit_salary_component(): void
    {
        $admin = $this->actingAsAdmin();
        $component = SalaryComponents::create([
            'name' => 'Tunjangan Transportasi',
            'category' => 'tunjangan',
            'calculation_type' => 'fixed',
            'default_value' => 100000,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ListSalaryComponents::class)
            ->callTableAction('edit', $component, data: [
                'name' => 'Tunjangan Transportasi',
                'category' => 'tunjangan',
                'calculation_type' => 'fixed',
                'default_value' => 150000,
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('salary_components', [
            'id' => $component->id,
            'default_value' => 150000,
        ]);
    }

    public function test_can_filter_by_company(): void
    {
        $admin = $this->actingAsAdmin();
        $companyA = Companies::create(['code' => 'A', 'name' => 'Rumah Sakit A', 'location' => 'Test', 'is_active' => true]);
        $companyB = Companies::create(['code' => 'B', 'name' => 'Rumah Sakit B', 'location' => 'Test', 'is_active' => true]);

        SalaryComponents::create([
            'name' => 'Jasa Pelayanan A',
            'category' => 'jasa_pelayanan',
            'calculation_type' => 'fixed',
            'company_id' => $companyA->id,
            'is_active' => true,
        ]);
        SalaryComponents::create([
            'name' => 'Jasa Pelayanan B',
            'category' => 'jasa_pelayanan',
            'calculation_type' => 'fixed',
            'company_id' => $companyB->id,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ListSalaryComponents::class)
            ->filterTable('company_id', $companyA->id)
            ->assertCanSeeTableRecords(SalaryComponents::where('company_id', $companyA->id)->get())
            ->assertCanNotSeeTableRecords(SalaryComponents::where('company_id', $companyB->id)->get());
    }

    public function test_delete_hidden_when_component_is_in_use(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $component = SalaryComponents::create([
            'name' => 'Jasa Pelayanan',
            'category' => 'jasa_pelayanan',
            'calculation_type' => 'fixed',
            'is_active' => true,
        ]);

        SalaryComponentAssignments::create([
            'salaryable_type' => Companies::class,
            'salaryable_id' => $company->id,
            'salary_component_id' => $component->id,
            'amount' => 5000000,
        ]);

        Livewire::actingAs($admin)
            ->test(ListSalaryComponents::class)
            ->assertTableActionHidden('delete', $component);
    }

    public function test_delete_visible_when_component_is_unused_and_not_a_general_category(): void
    {
        $admin = $this->actingAsAdmin();
        $component = SalaryComponents::create([
            'name' => 'Jasa Pelayanan',
            'category' => 'jasa_pelayanan',
            'calculation_type' => 'fixed',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ListSalaryComponents::class)
            ->assertTableActionVisible('delete', $component);
    }

    /**
     * @dataProvider generalCategoryProvider
     */
    public function test_delete_hidden_for_general_categories_even_when_unused(string $category): void
    {
        $admin = $this->actingAsAdmin();
        $component = SalaryComponents::create([
            'name' => 'Komponen Umum',
            'category' => $category,
            'calculation_type' => 'fixed',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ListSalaryComponents::class)
            ->assertTableActionHidden('delete', $component);
    }

    public static function generalCategoryProvider(): array
    {
        return [
            'Cuti' => ['cuti'],
            'Izin' => ['izin'],
            'Keterlambatan' => ['keterlambatan'],
            'Pajak Jasa Dokter' => ['pajak_jasa_dokter'],
        ];
    }
}
