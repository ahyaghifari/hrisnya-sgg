<?php

namespace Tests\Feature\Task;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Task\TaskListPage;
use App\Models\Master\Companies;
use App\Models\Task\Task;
use App\Models\User;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Tab filter di Board: "Semua" (default) vs "Tugas Saya" (cuma yang ditugaskan ke user login). */
class TaskBoardMyTasksFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_filter_defaults_to_all_and_can_switch_to_my_tasks(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::BUAT_BOARD_TUGAS->value, 'guard_name' => 'web']);
        $owner = User::factory()->create(['company_id' => $company->id]);
        $owner->givePermissionTo(CustomPermissionEnum::BUAT_BOARD_TUGAS->value);
        $member = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board Filter', 'member_ids' => [$member->id]]);
        $list = $project->lists()->orderBy('position')->first();

        $service->createCasualTask(['requirement' => 'Tugas Milik Owner', 'project_id' => $project->id, 'list_id' => $list->id, 'assigned_to' => $owner->id]);
        $service->createCasualTask(['requirement' => 'Tugas Milik Member', 'project_id' => $project->id, 'list_id' => $list->id, 'assigned_to' => $member->id]);

        $this->assertSame(2, Task::where('project_id', $project->id)->count());

        $page = Livewire::actingAs($owner)->test(TaskListPage::class, ['project' => $project]);

        $this->assertSame('semua', $page->get('boardFilter'));
        $page->assertSee('Tugas Milik Owner')->assertSee('Tugas Milik Member');

        $page->call('setBoardFilter', 'saya')
            ->assertSee('Tugas Milik Owner')
            ->assertDontSee('Tugas Milik Member');

        $page->call('setBoardFilter', 'semua')
            ->assertSee('Tugas Milik Member');
    }
}
