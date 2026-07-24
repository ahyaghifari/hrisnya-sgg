<?php

namespace Tests\Feature\LMS;

use App\Enum\LMS\TrainingCategoryEnum;
use App\Livewire\LMS\MasterHonorariumPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterHonorariumPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLmsAdmin(): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit_honorarium_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'LMS Honorarium Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms', 'edit_honorarium_lms']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(MasterHonorariumPage::class)->assertForbidden();
    }

    public function test_default_context_is_the_first_scored_case(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(MasterHonorariumPage::class)
            ->assertSet('context', TrainingCategoryEnum::scoredCases()[0]->value);
    }

    public function test_saves_variable(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(MasterHonorariumPage::class)
            ->set('context', TrainingCategoryEnum::DOKTER_SPESIALIS->value)
            ->set('newVariable.name', 'Lama Praktik')
            ->call('saveVariable')
            ->assertSee('Lama Praktik');

        $this->assertDatabaseHas('lms_honorarium_variables', [
            'name' => 'Lama Praktik',
            'context' => TrainingCategoryEnum::DOKTER_SPESIALIS->value,
        ]);
    }

    public function test_saves_category_under_a_variable(): void
    {
        $admin = $this->actingAsLmsAdmin();

        $component = Livewire::actingAs($admin)
            ->test(MasterHonorariumPage::class)
            ->set('context', TrainingCategoryEnum::DOKTER_SPESIALIS->value)
            ->set('newVariable.name', 'Lama Praktik')
            ->call('saveVariable');

        $variableId = \App\Models\LMS\Trainer\LmsHonorariumVariable::where('name', 'Lama Praktik')->value('id');

        $component->set('newCategory.variable_id', $variableId)
            ->set('newCategory.name', '< 10 Tahun')
            ->set('newCategory.score', 1)
            ->call('saveCategory')
            ->assertSee('< 10 Tahun');

        $this->assertDatabaseHas('lms_honorarium_categories', [
            'variable_id' => $variableId,
            'name' => '< 10 Tahun',
            'score' => 1,
        ]);
    }

    public function test_variables_are_scoped_per_context(): void
    {
        $admin = $this->actingAsLmsAdmin();

        $component = Livewire::actingAs($admin)
            ->test(MasterHonorariumPage::class)
            ->set('context', TrainingCategoryEnum::DOKTER_SPESIALIS->value)
            ->set('newVariable.name', 'Reputasi')
            ->call('saveVariable')
            ->assertSee('Reputasi');

        $component->set('context', TrainingCategoryEnum::EKSTERNAL->value)
            ->assertDontSee('Reputasi');
    }

    public function test_saves_range(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(MasterHonorariumPage::class)
            ->set('context', TrainingCategoryEnum::EKSTERNAL->value)
            ->set('rangeIsSingle', false)
            ->set('newRange.min_score', 5)
            ->set('newRange.max_score', 7)
            ->set('newRange.honor_amount', 500000)
            ->call('saveRange')
            ->assertSee('500.000');

        $this->assertDatabaseHas('lms_honorarium', [
            'context' => TrainingCategoryEnum::EKSTERNAL->value,
            'min_score' => 5,
            'max_score' => 7,
            'honor_amount' => 500000,
        ]);
    }

    public function test_saves_single_score_range_by_copying_min_into_max(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(MasterHonorariumPage::class)
            ->set('context', TrainingCategoryEnum::EKSTERNAL->value)
            ->assertSet('rangeIsSingle', true)
            ->set('newRange.min_score', 5)
            ->set('newRange.honor_amount', 1000000)
            ->call('saveRange');

        $this->assertDatabaseHas('lms_honorarium', [
            'context' => TrainingCategoryEnum::EKSTERNAL->value,
            'min_score' => 5,
            'max_score' => 5,
            'honor_amount' => 1000000,
        ]);
    }
}
