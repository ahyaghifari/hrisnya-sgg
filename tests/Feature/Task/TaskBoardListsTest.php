<?php

namespace Tests\Feature\Task;

use App\Enum\Task\TaskStatus;
use App\Models\Master\Companies;
use App\Models\Task\Task;
use App\Models\Task\TaskList;
use App\Models\User;
use App\Livewire\Task\TaskListPage;
use App\Notifications\TaskCompletedByAssignee;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * List custom ala Trello di Board (TaskProject) — beda dari 4 status fixed yang dipakai board
 * personal (Todo Saya/Tugas Saya/Individu, dites di TaskModuleTest.php, TIDAK disentuh di sini).
 * Lihat plan: List "Selesai" (is_final) setara status COMPLETE, reuse rateTask()/notifikasi lama.
 */
class TaskBoardListsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_new_project_gets_3_default_lists_with_last_one_final(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $project = app(TaskService::class)->createProject(['name' => 'Board A']);
        $lists = $project->lists()->orderBy('position')->get();

        $this->assertCount(3, $lists);
        $this->assertFalse($lists[0]->is_final);
        $this->assertFalse($lists[1]->is_final);
        $this->assertTrue($lists[2]->is_final);
    }

    public function test_list_management_is_owner_only(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board B', 'member_ids' => [$member->id]]);
        $list = $project->lists()->first();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->createList($project, 'List Baru', $member->fresh());
    }

    public function test_owner_can_create_rename_reorder_and_mark_list_final(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board C']);

        $newList = $service->createList($project, 'QA', $owner);
        $this->assertDatabaseHas('task_lists', ['id' => $newList->id, 'name' => 'QA']);

        $service->renameList($newList, 'Quality Assurance', $owner);
        $this->assertSame('Quality Assurance', $newList->fresh()->name);

        $ids = $project->lists()->orderBy('position')->pluck('id')->reverse()->values()->all();
        $service->reorderLists($project, $ids, $owner);
        $this->assertSame($ids, $project->lists()->orderBy('position')->pluck('id')->values()->all());

        $service->setListFinal($newList, $owner);
        $this->assertTrue($newList->fresh()->is_final);
        // Cuma boleh 1 List final per Board - List lain otomatis kebalik false.
        $this->assertSame(1, $project->lists()->where('is_final', true)->count());
    }

    public function test_delete_list_rejected_when_not_empty(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board D']);
        $list = $project->lists()->first();

        $service->createCasualTask([
            'requirement' => 'Ada di List ini',
            'project_id' => $project->id,
            'list_id' => $list->id,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->deleteList($list, $owner);
    }

    public function test_any_project_member_can_move_cards_across_lists(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board E', 'member_ids' => [$member->id]]);
        $lists = $project->lists()->orderBy('position')->get();

        $task = $service->createCasualTask([
            'requirement' => 'Task E1',
            'project_id' => $project->id,
            'list_id' => $lists[0]->id,
        ]);

        // Member biasa (bukan owner) boleh drag card antar List - beda dari kelola struktur List.
        $service->moveCardsAcrossLists([$lists[1]->id => [$task->id]], $member->fresh());

        $this->assertSame($lists[1]->id, $task->fresh()->list_id);
    }

    public function test_card_moved_into_final_list_becomes_complete_and_notifies_creator(): void
    {
        Notification::fake();

        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);
        Auth::login($creator);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board F', 'member_ids' => [$assignee->id]]);
        $lists = $project->lists()->orderBy('position')->get();
        $finalList = $lists->firstWhere('is_final', true);

        $task = $service->createCasualTask([
            'requirement' => 'Task F1',
            'assigned_to' => $assignee->id,
            'project_id' => $project->id,
            'list_id' => $lists[0]->id,
            'notify_creator_on_complete' => true,
        ]);

        $service->moveCardsAcrossLists([$finalList->id => [$task->id]], $assignee->fresh());
        $task = $task->fresh();

        $this->assertSame(TaskStatus::COMPLETE->value, $task->status);
        $this->assertNotNull($task->completed_date);
        Notification::assertSentTo($creator->fresh(), TaskCompletedByAssignee::class);
    }

    public function test_card_moved_out_of_final_list_reopens_without_24h_limit(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board G']);
        $lists = $project->lists()->orderBy('position')->get();
        $finalList = $lists->firstWhere('is_final', true);

        $task = $service->createCasualTask([
            'requirement' => 'Task G1',
            'project_id' => $project->id,
            'list_id' => $lists[0]->id,
        ]);

        $service->moveCardsAcrossLists([$finalList->id => [$task->id]], $owner);
        $this->assertSame(TaskStatus::COMPLETE->value, $task->fresh()->status);

        // Reopen jauh lebih dari 24 jam sesudahnya - tetap boleh (beda dari moveStatus() personal
        // scope yang punya batas 24 jam).
        $task->update(['completed_date' => now()->subDays(10)]);

        $service->moveCardsAcrossLists([$lists[0]->id => [$task->id]], $owner);
        $task = $task->fresh();

        $this->assertSame(TaskStatus::IN_PROGRESS->value, $task->status);
        $this->assertNull($task->completed_date);
    }

    public function test_rate_task_works_for_board_task_via_final_list(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        Auth::login($creator);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board H']);
        $lists = $project->lists()->orderBy('position')->get();
        $finalList = $lists->firstWhere('is_final', true);

        $task = $service->createCasualTask([
            'requirement' => 'Task H1',
            'project_id' => $project->id,
            'list_id' => $lists[0]->id,
        ]);

        $service->moveCardsAcrossLists([$finalList->id => [$task->id]], $creator);
        $service->rateTask($task->fresh(), 5, $creator);

        $this->assertSame(5, $task->fresh()->rating);
    }

    public function test_new_task_in_board_defaults_to_first_list(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board I']);
        $firstList = $project->lists()->orderBy('position')->first();

        $task = $service->createCasualTask([
            'requirement' => 'Task I1 tanpa list eksplisit',
            'project_id' => $project->id,
        ]);

        $this->assertSame($firstList->id, $task->list_id);
    }

    /** Render halaman Tugas penuh (bukan cuma manggil service) - board List custom + drag wiring. */
    public function test_task_list_page_renders_board_with_custom_lists(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board J']);
        $lists = $project->lists()->orderBy('position')->get();

        $service->createCasualTask([
            'requirement' => 'Task J1',
            'project_id' => $project->id,
            'list_id' => $lists[0]->id,
        ]);

        Livewire::actingAs($owner)
            ->test(TaskListPage::class, ['project' => $project])
            ->assertSee('Task J1')
            ->assertSee($lists[0]->name)
            ->assertSee($lists->firstWhere('is_final', true)->name);
    }
}
