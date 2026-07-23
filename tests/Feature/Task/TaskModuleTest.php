<?php

namespace Tests\Feature\Task;

use App\Enum\Task\TaskSettingEnum;
use App\Enum\Task\TaskStatus;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\Task\Task;
use App\Models\Task\TaskProjectMember;
use App\Models\User;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Cakupan inti unifikasi Task+Kanban (planning/cached-dreaming-ladybug.md): status transition
 * di board, task casual langsung TODO, dan default approval per company via Setting. Alur
 * formal (createFormalTask + approve lewat ApprovalFlowService) butuh setup MasterApprovalFlows
 * + org structure lengkap — sengaja tidak dites di sini, lihat catatan di akhir file.
 */
class TaskModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_casual_task_is_created_as_todo_and_self_assigned(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        $task = app(TaskService::class)->createCasualTask([
            'requirement' => 'Bikin laporan mingguan',
        ]);

        $this->assertSame(TaskStatus::TODO->value, $task->status);
        $this->assertSame($user->id, $task->assigned_to);
        $this->assertTrue((bool) $task->is_self_assigned);
    }

    public function test_casual_task_rejects_assigning_to_non_subordinate(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        $stranger = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(TaskService::class)->createCasualTask([
            'requirement' => 'Tugas buat orang lain',
            'assigned_to' => $stranger->id,
        ]);
    }

    public function test_move_status_only_one_step_forward(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        $task = Task::create([
            'code' => 'TSK00001',
            'company_id' => $company->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status' => TaskStatus::TODO->value,
        ]);

        $service = app(TaskService::class);

        $service->moveStatus($task, TaskStatus::IN_PROGRESS->value, $user);
        $this->assertSame(TaskStatus::IN_PROGRESS->value, $task->fresh()->status);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->moveStatus($task, TaskStatus::COMPLETE->value, $user);
    }

    public function test_move_status_allows_pulling_back_from_review_to_in_progress(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        $task = Task::create([
            'code' => 'TSK00002',
            'company_id' => $company->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status' => TaskStatus::REVIEW->value,
        ]);

        app(TaskService::class)->moveStatus($task, TaskStatus::IN_PROGRESS->value, $user);

        $this->assertSame(TaskStatus::IN_PROGRESS->value, $task->fresh()->status);
    }

    public function test_move_status_locked_when_pending_approval(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        $task = Task::create([
            'code' => 'TSK00003',
            'company_id' => $company->id,
            'created_by' => $user->id,
            'status' => TaskStatus::PENDING->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->moveStatus($task, TaskStatus::TODO->value, $user);
    }

    public function test_move_status_forbidden_for_non_owner(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $stranger = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00004',
            'company_id' => $company->id,
            'created_by' => $owner->id,
            'assigned_to' => $owner->id,
            'status' => TaskStatus::TODO->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->moveStatus($task, TaskStatus::IN_PROGRESS->value, $stranger);
    }

    public function test_requires_approval_default_follows_company_setting(): void
    {
        $company = $this->makeCompany();
        $service = app(TaskService::class);

        $this->assertFalse($service->requiresApprovalByDefault($company->id));

        Setting::create([
            'company_id' => $company->id,
            'module' => 'task',
            'key' => TaskSettingEnum::REQUIRE_APPROVAL_DEFAULT->value,
            'type' => 'boolean',
            'value' => '1',
            'label' => TaskSettingEnum::REQUIRE_APPROVAL_DEFAULT->label(),
        ]);

        $this->assertTrue($service->requiresApprovalByDefault($company->id));
    }

    public function test_creating_project_makes_creator_the_owner(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        $project = app(TaskService::class)->createProject(['name' => 'Project A']);

        $this->assertDatabaseHas('task_project_members', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => TaskProjectMember::ROLE_OWNER,
        ]);
        $this->assertSame($user->id, $project->owner_id);
    }

    public function test_only_owner_can_add_project_member(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $stranger = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Project B', 'member_ids' => [$member->id]]);

        $this->assertTrue($service->canAccessProject($project, $member->fresh()));
        $this->assertFalse($service->canAccessProject($project, $stranger->fresh()));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->addProjectMember($project, $stranger->id, $member->fresh());
    }

    public function test_task_can_be_assigned_to_fellow_project_member_not_just_subordinate(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $peer = User::factory()->create(['company_id' => $company->id]);
        Auth::login($creator);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Project C', 'member_ids' => [$peer->id]]);

        $task = $service->createCasualTask([
            'requirement' => 'Kerjaan project',
            'assigned_to' => $peer->id,
            'project_id' => $project->id,
        ]);

        $this->assertSame($peer->id, $task->assigned_to);
        $this->assertSame($project->id, $task->project_id);
    }

    public function test_task_cannot_be_assigned_to_non_project_member(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $outsider = User::factory()->create(['company_id' => $company->id]);
        Auth::login($creator);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Project D']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->createCasualTask([
            'requirement' => 'Kerjaan project',
            'assigned_to' => $outsider->id,
            'project_id' => $project->id,
        ]);
    }
}
