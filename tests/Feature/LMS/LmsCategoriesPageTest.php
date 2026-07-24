<?php

namespace Tests\Feature\LMS;

use App\Enum\LMS\TrainingCategoryEnum;
use App\Livewire\LMS\LmsCategoriesPage;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LmsCategoriesPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLmsAdmin(): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:LmsCategories', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:LmsCategories', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:LmsCategories', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'LMS Categories Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms', 'Create:LmsCategories', 'Update:LmsCategories', 'Delete:LmsCategories']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(LmsCategoriesPage::class)->assertForbidden();
    }

    public function test_creates_category(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(LmsCategoriesPage::class)
            ->set('formName', 'Pelayanan Prima')
            ->set('formTrainingCategory', TrainingCategoryEnum::UMUM->value)
            ->call('saveForm')
            ->assertSee('Pelayanan Prima');

        $this->assertDatabaseHas('lms_categories', [
            'name' => 'Pelayanan Prima',
            'training_category' => TrainingCategoryEnum::UMUM->value,
        ]);
    }

    public function test_creates_category_without_training_category(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(LmsCategoriesPage::class)
            ->set('formName', 'Belum Diklasifikasi')
            ->call('saveForm');

        $this->assertDatabaseHas('lms_categories', [
            'name' => 'Belum Diklasifikasi',
            'training_category' => null,
        ]);
    }

    public function test_updates_category(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'IT']);

        Livewire::actingAs($admin)
            ->test(LmsCategoriesPage::class)
            ->call('openEditModal', $category->id)
            ->set('formName', 'IT & Digitalisasi')
            ->set('formTrainingCategory', TrainingCategoryEnum::DOKTER_SPESIALIS->value)
            ->call('saveForm');

        $this->assertDatabaseHas('lms_categories', [
            'id' => $category->id,
            'name' => 'IT & Digitalisasi',
            'training_category' => TrainingCategoryEnum::DOKTER_SPESIALIS->value,
        ]);
    }

    public function test_rejects_eksternal_as_training_category(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(LmsCategoriesPage::class)
            ->set('formName', 'Kategori Uji')
            ->set('formTrainingCategory', TrainingCategoryEnum::EKSTERNAL->value)
            ->call('saveForm')
            ->assertHasErrors(['formTrainingCategory']);
    }

    public function test_deletes_category_without_courses(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'Kategori Kosong']);

        Livewire::actingAs($admin)
            ->test(LmsCategoriesPage::class)
            ->call('removeCategory', $category->id);

        $this->assertDatabaseMissing('lms_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_courses(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'Kategori Dipakai']);
        LmsCourses::create([
            'category_id' => $category->id,
            'code' => 'TRN-001',
            'title' => 'Pelatihan Uji',
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCategoriesPage::class)
            ->call('removeCategory', $category->id);

        $this->assertDatabaseHas('lms_categories', ['id' => $category->id]);
    }

    public function test_search_filters_by_name(): void
    {
        $admin = $this->actingAsLmsAdmin();
        LmsCategories::create(['name' => 'Kepegawaian']);
        LmsCategories::create(['name' => 'Keuangan']);

        Livewire::actingAs($admin)
            ->test(LmsCategoriesPage::class)
            ->set('search', 'Kepegawaian')
            ->assertSee('Kepegawaian')
            ->assertDontSee('Keuangan');
    }
}
