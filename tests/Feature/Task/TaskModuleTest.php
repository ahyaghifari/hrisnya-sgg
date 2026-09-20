<?php

namespace Tests\Feature\Task;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Task\TaskStatus;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Master\Companies;
use App\Models\Task\Task;
use App\Models\Task\TaskProjectMember;
use App\Models\User;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Cakupan inti unifikasi Task+Kanban (planning/cached-dreaming-ladybug.md): status transition
 * di board, task casual langsung TODO. Alur
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

    /**
     * BOARD_ORDER sekarang TODO -> IN_PROGRESS -> COMPLETE langsung (tanpa tahap REVIEW, dihapus
     * 2026-08-02 bareng TaskReview - satu-satunya pemakai, app mobile lama, sudah ditinggal).
     */
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
            'needs_review' => true,
            'status' => TaskStatus::TODO->value,
        ]);

        $service = app(TaskService::class);

        $service->moveStatus($task, TaskStatus::IN_PROGRESS->value, $user);
        $task = $task->fresh();
        $this->assertSame(TaskStatus::IN_PROGRESS->value, $task->status);

        $service->moveStatus($task, TaskStatus::REVIEW->value, $user);
        $task = $task->fresh();
        $this->assertSame(TaskStatus::REVIEW->value, $task->status);

        $service->moveStatus($task, TaskStatus::COMPLETE->value, $user);
        $this->assertSame(TaskStatus::COMPLETE->value, $task->fresh()->status);
    }

    public function test_move_status_skips_review_when_needs_review_false(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);
        Auth::login($assignee);

        $task = Task::create([
            'code' => 'TSK00099',
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'needs_review' => false,
            'status' => TaskStatus::IN_PROGRESS->value,
        ]);

        app(TaskService::class)->moveStatus($task, TaskStatus::COMPLETE->value, $assignee);

        $this->assertSame(TaskStatus::COMPLETE->value, $task->fresh()->status);
    }

    public function test_move_status_rejects_skipping_a_step(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        $task = Task::create([
            'code' => 'TSK00002',
            'company_id' => $company->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status' => TaskStatus::TODO->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->moveStatus($task, TaskStatus::COMPLETE->value, $user);
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
            'assigned_to' => $user->id,
            'status' => TaskStatus::TODO->value,
        ]);

        $flow = \App\Models\ApprovalManagement\MasterApprovalFlows::create([
            'company_id' => $company->id,
            'name' => 'Flow Test',
            'module' => ApprovalModuleEnum::TASK_REQUEST->value,
            'flow_type' => 'default',
            'is_active' => true,
        ]);

        ApprovalSnapshots::create([
            'module_type' => ApprovalModuleEnum::TASK_REQUEST->value,
            'module_id' => $task->id,
            'flow_id' => $flow->id,
            'flow_name' => $flow->name,
            'steps_snapshot' => [],
            'status' => 'in_review',
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->moveStatus($task, TaskStatus::IN_PROGRESS->value, $user);
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

    public function test_move_status_at_todo_only_assignee_allowed(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00005',
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'status' => TaskStatus::TODO->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->moveStatus($task, TaskStatus::IN_PROGRESS->value, $creator);
    }

    public function test_move_status_review_can_go_back_to_in_progress(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00006',
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'needs_review' => true,
            'status' => TaskStatus::REVIEW->value,
        ]);

        app(TaskService::class)->moveStatus($task, TaskStatus::IN_PROGRESS->value, $creator);

        $this->assertSame(TaskStatus::IN_PROGRESS->value, $task->fresh()->status);
    }

    public function test_move_status_at_review_only_creator_allowed(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00007',
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'needs_review' => true,
            'status' => TaskStatus::REVIEW->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->moveStatus($task, TaskStatus::COMPLETE->value, $assignee);
    }

    public function test_rate_task_only_creator_after_complete(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00008',
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'status' => TaskStatus::COMPLETE->value,
        ]);

        app(TaskService::class)->rateTask($task, 4, $creator);
        $this->assertSame(4, $task->fresh()->rating);

        $notCompleteTask = Task::create([
            'code' => 'TSK00009',
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'status' => TaskStatus::TODO->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->rateTask($notCompleteTask, 5, $creator);
    }

    public function test_rate_task_forbidden_for_non_creator(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00010',
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'status' => TaskStatus::COMPLETE->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->rateTask($task, 3, $assignee);
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

    public function test_project_member_without_assigner_flag_cannot_assign_to_others(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $peer = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Project E', 'member_ids' => [$member->id, $peer->id]]);

        Auth::login($member->fresh());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->createCasualTask([
            'requirement' => 'Kerjaan buat peer',
            'assigned_to' => $peer->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_project_member_without_assigner_flag_can_still_self_assign(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Project F', 'member_ids' => [$member->id]]);

        Auth::login($member->fresh());

        $task = $service->createCasualTask([
            'requirement' => 'Kerjaan sendiri',
            'assigned_to' => $member->id,
            'project_id' => $project->id,
        ]);

        $this->assertSame($member->id, $task->assigned_to);
    }

    public function test_project_member_with_assigner_flag_can_assign_to_others(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $peer = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Project G', 'member_ids' => [$member->id, $peer->id]]);

        $service->setProjectMemberAssigner($project, $member->id, true, $owner);

        Auth::login($member->fresh());

        $task = $service->createCasualTask([
            'requirement' => 'Kerjaan buat peer',
            'assigned_to' => $peer->id,
            'project_id' => $project->id,
        ]);

        $this->assertSame($peer->id, $task->assigned_to);
    }

    public function test_only_owner_can_set_assigner_flag(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $another = User::factory()->create(['company_id' => $company->id]);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Project H', 'member_ids' => [$member->id, $another->id]]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->setProjectMemberAssigner($project, $another->id, true, $member->fresh());
    }

    public function test_file_attachment_can_be_added_and_removed(): void
    {
        Storage::fake('public');

        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        $task = app(TaskService::class)->createCasualTask(['requirement' => 'Kerjaan dengan lampiran']);

        $file = UploadedFile::fake()->create('dokumen.pdf', 100);
        app(TaskService::class)->addFileAttachment($task, $user, $file);

        $attachment = $task->attachments()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('dokumen.pdf', $attachment->file_name);
        Storage::disk('public')->assertExists($attachment->file_path);

        app(TaskService::class)->removeAttachment($attachment->id);

        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing($attachment->file_path);
    }

    public function test_link_attachment_can_be_added(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        $task = app(TaskService::class)->createCasualTask(['requirement' => 'Kerjaan dengan link']);

        app(TaskService::class)->addLinkAttachment($task, $user, 'https://example.com/dokumen', 'Dokumen Referensi');

        $this->assertDatabaseHas('task_attachments', [
            'task_id' => $task->id,
            'type' => 'link',
            'url' => 'https://example.com/dokumen',
            'file_name' => 'Dokumen Referensi',
        ]);
    }

    /**
     * 3 scope portal (tab horizontal, 2026-08-02): 'todo' = catatan pribadi (self-assigned),
     * 'saya' = SEMUA yang assigned ke aku (superset dari 'todo', termasuk yang di-assign orang
     * lain), 'individu' = "Penugasan Individu" - yang AKU assign ke orang lain.
     */
    public function test_portal_scopes_todo_saya_and_individu(): void
    {
        $company = $this->makeCompany();
        $boss = User::factory()->create(['company_id' => $company->id]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        Auth::login($boss);

        $todo = Task::create([
            'code' => 'TSK00010', 'company_id' => $company->id,
            'created_by' => $boss->id, 'assigned_to' => $boss->id,
            'is_self_assigned' => true, 'status' => TaskStatus::TODO->value,
        ]);
        $assignedByOther = Task::create([
            'code' => 'TSK00011', 'company_id' => $company->id,
            'created_by' => $staff->id, 'assigned_to' => $boss->id,
            'is_self_assigned' => false, 'status' => TaskStatus::TODO->value,
        ]);
        $individu = Task::create([
            'code' => 'TSK00012', 'company_id' => $company->id,
            'created_by' => $boss->id, 'assigned_to' => $staff->id,
            'is_self_assigned' => false, 'status' => TaskStatus::TODO->value,
        ]);

        $service = app(TaskService::class);

        // 'todo' cuma yang self-assigned.
        $this->assertEqualsCanonicalizing([$todo->id], $service->getPortalTasks($boss, 'todo')->pluck('id')->all());

        // 'saya' = yang di-assign ORANG LAIN ke aku, bukan self-assigned (dipisah dari 'todo').
        $this->assertEqualsCanonicalizing(
            [$assignedByOther->id],
            $service->getPortalTasks($boss, 'saya')->pluck('id')->all()
        );

        // 'individu' = yang AKU assign ke orang lain.
        $this->assertEqualsCanonicalizing([$individu->id], $service->getPortalTasks($boss, 'individu')->pluck('id')->all());
    }

    public function test_portal_scope_tim_shows_tasks_assigned_to_subordinates(): void
    {
        $company = $this->makeCompany();
        $hcManager = User::factory()->create(['company_id' => $company->id]);
        $supervisor = User::factory()->create(['company_id' => $company->id]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        $outsider = User::factory()->create(['company_id' => $company->id]);

        // Fake OrgHierarchyService::subordinates() - hierarchy nyata (JobPosition/OrgStructure)
        // ngga dites di file ini, terlalu berat buat setup; scope 'tim' cuma butuh subordinates()
        // ngembaliin query User yang bener.
        $fakeOrgHierarchy = \Mockery::mock(\App\Services\OrgHierarchyService::class);
        $fakeOrgHierarchy->shouldReceive('subordinates')
            ->andReturn(User::query()->whereIn('id', [$supervisor->id, $staff->id]));
        $this->app->instance(\App\Services\OrgHierarchyService::class, $fakeOrgHierarchy);

        $doneBySupervisor = Task::create([
            'code' => 'TSK00013', 'company_id' => $company->id,
            'created_by' => $hcManager->id, 'assigned_to' => $supervisor->id,
            'status' => TaskStatus::TODO->value,
        ]);
        $doneByStaffForPeer = Task::create([
            'code' => 'TSK00014', 'company_id' => $company->id,
            'created_by' => $supervisor->id, 'assigned_to' => $staff->id,
            'status' => TaskStatus::TODO->value,
        ]);
        $doneByOutsider = Task::create([
            'code' => 'TSK00015', 'company_id' => $company->id,
            'created_by' => $hcManager->id, 'assigned_to' => $outsider->id,
            'status' => TaskStatus::TODO->value,
        ]);

        $tasks = app(TaskService::class)->getPortalTasks($hcManager, 'tim');

        $this->assertEqualsCanonicalizing(
            [$doneBySupervisor->id, $doneByStaffForPeer->id],
            $tasks->pluck('id')->all()
        );
        $this->assertFalse($tasks->contains('id', $doneByOutsider->id));
    }

    public function test_history_tasks_only_shows_completed_older_than_a_day(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $recentComplete = Task::create([
            'code' => 'TSK00020', 'company_id' => $company->id,
            'created_by' => $user->id, 'assigned_to' => $user->id, 'is_self_assigned' => true,
            'status' => TaskStatus::COMPLETE->value, 'completed_date' => now()->subHours(2),
        ]);
        $oldComplete = Task::create([
            'code' => 'TSK00021', 'company_id' => $company->id,
            'created_by' => $user->id, 'assigned_to' => $user->id, 'is_self_assigned' => true,
            'status' => TaskStatus::COMPLETE->value, 'completed_date' => now()->subDays(3),
        ]);
        $stillTodo = Task::create([
            'code' => 'TSK00022', 'company_id' => $company->id,
            'created_by' => $user->id, 'assigned_to' => $user->id, 'is_self_assigned' => true,
            'status' => TaskStatus::TODO->value,
        ]);

        $service = app(TaskService::class);

        // Board (getPortalTasks): recent-complete masih nongol, yang lama udah ngga.
        $boardIds = $service->getPortalTasks($user, 'todo')->pluck('id')->all();
        $this->assertContains($recentComplete->id, $boardIds);
        $this->assertContains($stillTodo->id, $boardIds);
        $this->assertNotContains($oldComplete->id, $boardIds);

        // Riwayat (getHistoryTasks): kebalikannya.
        $historyIds = $service->getHistoryTasks($user, 'todo')->pluck('id')->all();
        $this->assertContains($oldComplete->id, $historyIds);
        $this->assertNotContains($recentComplete->id, $historyIds);
        $this->assertNotContains($stillTodo->id, $historyIds);
    }

    public function test_delete_task_self_assigned_always_allowed(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00023', 'company_id' => $company->id,
            'created_by' => $user->id, 'assigned_to' => $user->id, 'is_self_assigned' => true,
            'status' => TaskStatus::COMPLETE->value,
        ]);

        app(TaskService::class)->deleteTask($task, $user);

        $this->assertDatabaseMissing('task_records', ['id' => $task->id]);
    }

    public function test_delete_task_individu_blocked_after_review(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00024', 'company_id' => $company->id,
            'created_by' => $creator->id, 'assigned_to' => $assignee->id,
            'needs_review' => true, 'status' => TaskStatus::REVIEW->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->deleteTask($task, $creator);
    }

    public function test_delete_task_individu_allowed_before_review(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00025', 'company_id' => $company->id,
            'created_by' => $creator->id, 'assigned_to' => $assignee->id,
            'status' => TaskStatus::IN_PROGRESS->value,
        ]);

        app(TaskService::class)->deleteTask($task, $creator);

        $this->assertDatabaseMissing('task_records', ['id' => $task->id]);
    }

    public function test_update_task_details_locked_after_complete(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00026', 'company_id' => $company->id,
            'created_by' => $creator->id, 'assigned_to' => $assignee->id,
            'requirement' => 'lama', 'status' => TaskStatus::IN_PROGRESS->value,
        ]);

        app(TaskService::class)->updateTaskDetails($task, [
            'requirement' => 'baru', 'priority' => 'high', 'due_date' => null,
        ], $creator);
        $this->assertSame('baru', $task->fresh()->requirement);

        $task->update(['status' => TaskStatus::COMPLETE->value]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->updateTaskDetails($task, [
            'requirement' => 'gagal', 'priority' => 'low', 'due_date' => null,
        ], $creator);
    }

    public function test_move_status_revert_complete_within_24h_for_no_review_task(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00027', 'company_id' => $company->id,
            'created_by' => $creator->id, 'assigned_to' => $assignee->id,
            'needs_review' => false, 'status' => TaskStatus::COMPLETE->value,
            'completed_date' => now()->subHours(5),
        ]);

        app(TaskService::class)->moveStatus($task, TaskStatus::IN_PROGRESS->value, $creator);

        $this->assertSame(TaskStatus::IN_PROGRESS->value, $task->fresh()->status);
    }

    public function test_move_status_revert_complete_blocked_after_24h(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00028', 'company_id' => $company->id,
            'created_by' => $creator->id, 'assigned_to' => $assignee->id,
            'needs_review' => false, 'status' => TaskStatus::COMPLETE->value,
            'completed_date' => now()->subHours(30),
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->moveStatus($task, TaskStatus::IN_PROGRESS->value, $creator);
    }
}
