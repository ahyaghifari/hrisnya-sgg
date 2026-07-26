<?php

namespace Tests\Feature\LMS;

use App\Livewire\LMS\TrainersPage;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsMeetings;
use App\Models\LMS\Trainer\Trainers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Pemateri" — halaman portal pertama buat model Trainers, yang sebelumnya tidak punya UI
 * create/edit sama sekali (dropdown "Pilih Trainer" di admin cuma listing). Otorisasi CRUD
 * lewat Policy Shield (ViewAny/Create/Update/Delete:Trainers), bukan permission manual.
 */
class TrainersPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLmsAdmin(): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:Trainers', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Trainers', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:Trainers', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Trainers Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms', 'Create:Trainers', 'Update:Trainers', 'Delete:Trainers']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(TrainersPage::class)->assertForbidden();
    }

    public function test_creates_external_trainer(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(TrainersPage::class)
            ->set('formName', 'Dr. Budi')
            ->set('formType', 'external')
            ->set('formExpertise', 'Kardiologi')
            ->call('saveForm')
            ->assertSee('Dr. Budi');

        $this->assertDatabaseHas('trainers', [
            'name' => 'Dr. Budi',
            'type' => 'external',
            'expertise' => 'Kardiologi',
        ]);
    }

    public function test_creates_internal_trainer_linked_to_employee(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $employee = User::factory()->create(['name' => 'Siti']);

        Livewire::actingAs($admin)
            ->test(TrainersPage::class)
            ->set('formName', 'Siti')
            ->set('formType', 'internal')
            ->set('formUserId', $employee->id)
            ->call('saveForm');

        $this->assertDatabaseHas('trainers', [
            'name' => 'Siti',
            'type' => 'internal',
            'user_id' => $employee->id,
        ]);
    }

    public function test_updates_trainer(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $trainer = Trainers::create(['name' => 'Lama', 'type' => 'external']);

        Livewire::actingAs($admin)
            ->test(TrainersPage::class)
            ->call('openEditModal', $trainer->id)
            ->set('formName', 'Baru')
            ->call('saveForm');

        $this->assertDatabaseHas('trainers', ['id' => $trainer->id, 'name' => 'Baru']);
    }

    public function test_deletes_unused_trainer(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $trainer = Trainers::create(['name' => 'Tidak Terpakai', 'type' => 'external']);

        Livewire::actingAs($admin)
            ->test(TrainersPage::class)
            ->call('removeTrainer', $trainer->id);

        $this->assertDatabaseMissing('trainers', ['id' => $trainer->id]);
    }

    public function test_cannot_delete_trainer_used_in_meeting(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $trainer = Trainers::create(['name' => 'Terpakai', 'type' => 'external']);
        $course = LmsCourses::create(['code' => 'TRN-001', 'title' => 'Pelatihan Uji']);
        LmsMeetings::create([
            'course_id' => $course->id,
            'trainer_id' => $trainer->id,
            'title' => 'Sesi 1',
        ]);

        Livewire::actingAs($admin)
            ->test(TrainersPage::class)
            ->call('removeTrainer', $trainer->id);

        $this->assertDatabaseHas('trainers', ['id' => $trainer->id]);
    }

    public function test_search_filters_by_name(): void
    {
        $admin = $this->actingAsLmsAdmin();
        Trainers::create(['name' => 'Ahmad', 'type' => 'external']);
        Trainers::create(['name' => 'Budiman', 'type' => 'external']);

        Livewire::actingAs($admin)
            ->test(TrainersPage::class)
            ->set('search', 'Ahmad')
            ->assertSee('Ahmad')
            ->assertDontSee('Budiman');
    }

    public function test_toggle_active_flips_status_instead_of_delete(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $trainer = Trainers::create(['name' => 'Terpakai', 'type' => 'external', 'is_active' => true]);
        $course = LmsCourses::create(['code' => 'TRN-002', 'title' => 'Pelatihan Uji 2']);
        LmsMeetings::create(['course_id' => $course->id, 'trainer_id' => $trainer->id, 'title' => 'Sesi 1']);

        Livewire::actingAs($admin)
            ->test(TrainersPage::class)
            ->call('toggleActive', $trainer->id);

        $this->assertFalse($trainer->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(TrainersPage::class)
            ->call('toggleActive', $trainer->id);

        $this->assertTrue($trainer->fresh()->is_active);
    }

    public function test_default_tab_shows_only_active_trainers(): void
    {
        $admin = $this->actingAsLmsAdmin();
        Trainers::create(['name' => 'Aktif Saja', 'type' => 'external', 'is_active' => true]);
        Trainers::create(['name' => 'Nonaktif Dulu', 'type' => 'external', 'is_active' => false]);

        $component = Livewire::actingAs($admin)->test(TrainersPage::class);

        $component->assertSee('Aktif Saja')->assertDontSee('Nonaktif Dulu');

        $component->call('setStatus', 'tidak_aktif')
            ->assertDontSee('Aktif Saja')
            ->assertSee('Nonaktif Dulu');
    }

    public function test_pagination_limits_to_ten_per_page(): void
    {
        $admin = $this->actingAsLmsAdmin();
        for ($i = 1; $i <= 15; $i++) {
            Trainers::create(['name' => "Pemateri {$i}", 'type' => 'external']);
        }

        $component = Livewire::actingAs($admin)->test(TrainersPage::class);

        $this->assertCount(10, $component->instance()->trainers->items());
    }
}
