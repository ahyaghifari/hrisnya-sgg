<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Assessment\CreateTemplatePenilaianPage;
use App\Livewire\Assessment\EditTemplatePenilaianPage;
use App\Livewire\Assessment\TemplatePenilaianPage;
use App\Models\Assessment\AssessmentTemplateItems;
use App\Models\Assessment\AssessmentTemplates;
use App\Models\Assessment\AssessmentTypes;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TemplatePenilaianPageTest extends TestCase
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

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(TemplatePenilaianPage::class)->assertForbidden();
    }

    public function test_pengelola_can_create_template_with_items(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $type = AssessmentTypes::create(['name' => 'Tahunan']);
        $admin = $this->actingAsPengelola($company->id);

        $this->actingAs($admin);

        Livewire::test(CreateTemplatePenilaianPage::class)
            ->set('name', 'Template Uji')
            ->set('type_id', $type->id)
            ->set('items.0.name', 'Kedisiplinan')
            ->set('items.0.weight_percent', 100)
            ->call('submit')
            ->assertHasNoErrors();

        $template = AssessmentTemplates::where('name', 'Template Uji')->first();

        $this->assertNotNull($template);
        $this->assertSame($company->id, $template->company_id);
        $this->assertSame(1, $template->templateItems()->count());
    }

    public function test_pengelola_can_edit_template_and_remove_item(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $type = AssessmentTypes::create(['name' => 'Tahunan']);
        $admin = $this->actingAsPengelola($company->id);

        $this->actingAs($admin);

        $template = AssessmentTemplates::create(['company_id' => $company->id, 'type_id' => $type->id, 'name' => 'Template Lama']);
        $itemA = AssessmentTemplateItems::create(['template_id' => $template->id, 'sort_order' => 1, 'name' => 'Item A', 'max_score' => 5]);
        AssessmentTemplateItems::create(['template_id' => $template->id, 'sort_order' => 2, 'name' => 'Item B', 'max_score' => 5]);

        Livewire::test(EditTemplatePenilaianPage::class, ['template' => $template])
            ->set('name', 'Template Baru')
            ->call('removeItem', 1)
            ->call('submit')
            ->assertHasNoErrors();

        $template->refresh();

        $this->assertSame('Template Baru', $template->name);
        $this->assertSame(1, $template->templateItems()->count());
        $this->assertSame($itemA->id, $template->templateItems()->first()->id);
    }
}
