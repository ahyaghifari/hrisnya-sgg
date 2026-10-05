<?php

namespace Tests\Feature\Task;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\Task\Projects\Pages\EditTaskProject;
use App\Filament\Resources\Task\Projects\Pages\ListTaskProjects;
use App\Filament\Resources\Task\Projects\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Task\Tasks\Pages\EditTask;
use App\Filament\Resources\Task\Tasks\Pages\ListTasks;
use App\Models\Master\Companies;
use App\Models\Task\Task;
use App\Models\User;
use App\Services\Task\TaskService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskAdminResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_individual_tasks_and_board_tasks_separately_and_edits_a_task(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
        $admin = User::factory()->create(['company_id' => $company->id]);

        foreach (['Task', 'TaskProject'] as $subject) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete'] as $verb) {
                Permission::findOrCreate("{$verb}:{$subject}", 'web');
                $admin->givePermissionTo("{$verb}:{$subject}");
            }
        }
        Permission::findOrCreate(CustomPermissionEnum::BUAT_BOARD_TUGAS->value, 'web');
        $admin->givePermissionTo(CustomPermissionEnum::BUAT_BOARD_TUGAS->value);

        Auth::login($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board Admin']);
        $boardTask = $service->createCasualTask([
            'requirement' => 'Tugas di board',
            'project_id' => $project->id,
            'due_date' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);
        $individual = Task::create([
            'code' => 'IND-1',
            'requirement' => 'Tugas individu',
            'status' => 'todo',
            'priority' => 'medium',
            'created_by' => $admin->id,
            'assigned_to' => $admin->id,
            'is_self_assigned' => false,
            'company_id' => $company->id,
        ]);

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$individual])
            ->assertCanNotSeeTableRecords([$boardTask]);

        Livewire::test(ListTaskProjects::class)->assertCanSeeTableRecords([$project]);

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditTaskProject::class])
            ->assertCanSeeTableRecords([$boardTask]);

        Livewire::test(EditTask::class, ['record' => $boardTask->getKey()])
            ->fillForm(['requirement' => 'Judul diubah admin', 'priority' => 'high'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Judul diubah admin', $boardTask->fresh()->requirement);
    }
}
