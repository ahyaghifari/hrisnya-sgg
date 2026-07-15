<?php

namespace Tests\Feature;

use App\Livewire\Kanban\CreateKanbanBoard;
use App\Livewire\Kanban\KanbanBoardDetail;
use App\Livewire\Kanban\KanbanBoardsList;
use App\Livewire\Kanban\KanbanPersonalTasks;
use App\Models\Kanban\KanbanBoard;
use App\Models\Kanban\KanbanBoardMember;
use App\Models\Kanban\KanbanTask;
use App\Models\Kanban\KanbanTaskComment;
use App\Models\Kanban\KanbanTaskMember;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Services\Kanban\KanbanAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class KanbanModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Companies
    {
        return Companies::create([
            'code' => 'TST',
            'name' => 'Test Company',
            'location' => 'Test',
            'is_active' => true,
        ]);
    }

    /**
     * Bikin supervisor yang punya bawahan (hasSubordinates() = true), dan staff biasa
     * yang tidak punya bawahan — pola sama seperti AttendanceApprovalPortalTest.
     */
    private function makeSupervisorAndStaff(Companies $company): array
    {
        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $supervisor = User::factory()->create(['company_id' => $company->id]);
        $staff = User::factory()->create(['company_id' => $company->id]);

        UserJobPositions::create([
            'user_id' => $supervisor->id,
            'job_position_id' => $managerPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
        UserJobPositions::create([
            'user_id' => $staff->id,
            'job_position_id' => $staffPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);

        return compact('supervisor', 'staff');
    }

    private function makeBoardWithOwner(Companies $company, User $owner): KanbanBoard
    {
        $board = KanbanBoard::create([
            'company_id' => $company->id,
            'name' => 'Board Test',
            'description' => 'Deskripsi board test',
            'owner_id' => $owner->id,
        ]);

        KanbanBoardMember::create([
            'board_id' => $board->id,
            'user_id' => $owner->id,
            'role' => KanbanBoardMember::ROLE_OWNER,
        ]);

        return $board;
    }

    // ── MODEL & RELASI ─────────────────────────────────────────

    public function test_board_belongs_to_company_and_owner(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);

        $this->assertTrue($board->company->is($company));
        $this->assertTrue($board->owner->is($owner));
        $this->assertCount(1, $board->members);
    }

    public function test_task_null_board_id_is_personal_task(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $personalTask = KanbanTask::create([
            'board_id' => null,
            'title' => 'Task Pribadi',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $boardTask = KanbanTask::create([
            'board_id' => $this->makeBoardWithOwner($company, $user)->id,
            'title' => 'Task Board',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $this->assertTrue($personalTask->isPersonal());
        $this->assertFalse($boardTask->isPersonal());
    }

    public function test_task_default_status_and_priority(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $task = KanbanTask::create([
            'board_id' => null,
            'title' => 'Task Default',
            'created_by' => $user->id,
        ]);

        $this->assertSame(KanbanTask::STATUS_TODO, $task->status);
        $this->assertSame(KanbanTask::PRIORITY_MEDIUM, $task->priority);
    }

    public function test_task_members_comments_attachments_relations(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        $task = KanbanTask::create(['board_id' => null, 'title' => 'Task', 'created_by' => $user->id]);

        KanbanTaskMember::create(['task_id' => $task->id, 'user_id' => $user->id]);
        KanbanTaskComment::create(['task_id' => $task->id, 'user_id' => $user->id, 'comment' => 'Halo']);
        \App\Models\Kanban\KanbanTaskAttachment::create([
            'task_id' => $task->id,
            'uploaded_by' => $user->id,
            'file_path' => 'kanban/fake/path.png',
            'file_name' => 'path.png',
        ]);

        $task->refresh();

        $this->assertCount(1, $task->members);
        $this->assertCount(1, $task->comments);
        $this->assertCount(1, $task->attachments);
    }

    public function test_deleting_board_cascades_to_members_and_tasks(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $owner->id]);

        $boardId = $board->id;
        $board->forceDelete();

        $this->assertDatabaseMissing('kanban_board_members', ['board_id' => $boardId]);
        $this->assertDatabaseMissing('kanban_tasks', ['board_id' => $boardId]);
    }

    // ── KanbanAuthorizationService ─────────────────────────────

    public function test_can_create_board_is_open_to_any_user(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor, 'staff' => $staff] = $this->makeSupervisorAndStaff($company);
        $service = app(KanbanAuthorizationService::class);

        $this->assertTrue($service->canCreateBoard($supervisor));
        $this->assertTrue($service->canCreateBoard($staff));
    }

    public function test_can_access_board_requires_membership(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $stranger = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        $service = app(KanbanAuthorizationService::class);

        $this->assertTrue($service->canAccessBoard($board, $owner));
        $this->assertFalse($service->canAccessBoard($board, $stranger));
    }

    public function test_only_owner_can_add_member_or_promote_to_assigner(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $assigner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $assigner->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);
        $service = app(KanbanAuthorizationService::class);

        $this->assertTrue($service->canAddMember($board, $owner));
        $this->assertFalse($service->canAddMember($board, $assigner));
        $this->assertTrue($service->canPromoteToAssigner($board, $owner));
        $this->assertFalse($service->canPromoteToAssigner($board, $assigner));
    }

    public function test_owner_and_assigner_can_assign_to_others_but_member_cannot(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $assigner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $assigner->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $member->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        $service = app(KanbanAuthorizationService::class);

        $this->assertTrue($service->canAssignToOthers($board, $owner));
        $this->assertTrue($service->canAssignToOthers($board, $assigner));
        $this->assertFalse($service->canAssignToOthers($board, $member));
    }

    public function test_only_creator_or_board_owner_can_delete_task(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $assigner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $assigner->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);

        $taskByAssigner = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $assigner->id]);
        $service = app(KanbanAuthorizationService::class);

        $this->assertTrue($service->canDeleteTask($taskByAssigner, $assigner)); // pembuat
        $this->assertTrue($service->canDeleteTask($taskByAssigner, $owner)); // owner board
        $this->assertFalse($service->canDeleteTask($taskByAssigner, $owner === $assigner ? $owner : User::factory()->create(['company_id' => $company->id])));
    }

    public function test_personal_task_can_only_be_deleted_by_creator(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $other = User::factory()->create(['company_id' => $company->id]);
        $task = KanbanTask::create(['board_id' => null, 'title' => 'Task Pribadi', 'created_by' => $creator->id]);
        $service = app(KanbanAuthorizationService::class);

        $this->assertTrue($service->canDeleteTask($task, $creator));
        $this->assertFalse($service->canDeleteTask($task, $other));
    }

    // ── CreateKanbanBoard (Livewire) ───────────────────────────

    public function test_supervisor_can_create_board_and_becomes_owner(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor] = $this->makeSupervisorAndStaff($company);

        Livewire::actingAs($supervisor)
            ->test(CreateKanbanBoard::class)
            ->set('name', 'Board Rekrutmen')
            ->set('description', 'Board buat tim rekrutmen')
            ->call('submit');

        $this->assertDatabaseHas('kanban_boards', ['name' => 'Board Rekrutmen', 'owner_id' => $supervisor->id]);
        $board = KanbanBoard::where('name', 'Board Rekrutmen')->firstOrFail();
        $this->assertDatabaseHas('kanban_board_members', [
            'board_id' => $board->id,
            'user_id' => $supervisor->id,
            'role' => KanbanBoardMember::ROLE_OWNER,
        ]);
    }

    public function test_staff_without_subordinates_can_open_create_board_page(): void
    {
        $company = $this->makeCompany();
        ['staff' => $staff] = $this->makeSupervisorAndStaff($company);

        $this->actingAs($staff)
            ->get(route('kanban.create'))
            ->assertOk();
    }

    // ── KanbanBoardDetail (Livewire) ───────────────────────────

    public function test_member_cannot_create_task(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $member->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);

        Livewire::actingAs($member)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->set('title', 'Task oleh member')
            ->call('createTask')
            ->assertForbidden();

        $this->assertDatabaseMissing('kanban_tasks', ['title' => 'Task oleh member']);
    }

    public function test_member_cannot_open_create_task_modal(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $member->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);

        Livewire::actingAs($member)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('openCreateTask')
            ->assertForbidden();
    }

    public function test_assigner_can_assign_task_to_other_member_and_task_members_synced(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $assigner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $assigner->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $member->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);

        Livewire::actingAs($assigner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->set('title', 'Task buat member')
            ->set('assignedUsers', [$member->id])
            ->call('createTask');

        $task = KanbanTask::where('title', 'Task buat member')->firstOrFail();
        $this->assertSame($member->id, $task->assigned_to);
        // Konsistensi assigned_to <-> kanban_task_members (dikonfirmasi wajib di knowledge doc).
        $this->assertDatabaseHas('kanban_task_members', ['task_id' => $task->id, 'user_id' => $member->id]);
    }

    public function test_task_can_have_multiple_assignees(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $memberA = User::factory()->create(['company_id' => $company->id]);
        $memberB = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $memberA->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $memberB->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->set('title', 'Task berdua')
            ->set('assignedUsers', [$memberA->id, $memberB->id])
            ->call('createTask');

        $task = KanbanTask::where('title', 'Task berdua')->firstOrFail();
        $this->assertDatabaseHas('kanban_task_members', ['task_id' => $task->id, 'user_id' => $memberA->id]);
        $this->assertDatabaseHas('kanban_task_members', ['task_id' => $task->id, 'user_id' => $memberB->id]);
    }

    public function test_creator_cannot_assign_task_to_self(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->set('title', 'Task buat diri sendiri')
            ->set('assignedUsers', [$owner->id])
            ->call('createTask')
            ->assertHasErrors('assignedUsers.0');

        $this->assertDatabaseMissing('kanban_tasks', ['title' => 'Task buat diri sendiri']);
    }

    public function test_move_task_status_updates_completed_at(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('moveTaskStatus', $task->id, KanbanTask::STATUS_DONE);

        $task->refresh();
        $this->assertSame(KanbanTask::STATUS_DONE, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_member_can_only_advance_status_one_step(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $creator = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $creator->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $member->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $creator->id]);

        // Member (bukan owner/pembuat) coba lompat todo -> done, ditolak.
        Livewire::actingAs($member)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('moveTaskStatus', $task->id, KanbanTask::STATUS_DONE);
        $this->assertSame(KanbanTask::STATUS_TODO, $task->fresh()->status);

        // Member maju 1 step (todo -> in_progress), diterima.
        Livewire::actingAs($member)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('moveTaskStatus', $task->id, KanbanTask::STATUS_IN_PROGRESS);
        $this->assertSame(KanbanTask::STATUS_IN_PROGRESS, $task->fresh()->status);
    }

    public function test_assignee_can_withdraw_own_task_from_review_back_to_in_progress(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $creator = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $creator->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $member->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $creator->id, 'status' => KanbanTask::STATUS_REVIEW]);
        \App\Models\Kanban\KanbanTaskMember::create(['task_id' => $task->id, 'user_id' => $member->id]);

        // Assignee task boleh menarik mundur pengajuan review miliknya sendiri.
        Livewire::actingAs($member)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('moveTaskStatus', $task->id, KanbanTask::STATUS_IN_PROGRESS);
        $this->assertSame(KanbanTask::STATUS_IN_PROGRESS, $task->fresh()->status);
    }

    public function test_non_assignee_member_cannot_pull_others_task_back_from_review(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $creator = User::factory()->create(['company_id' => $company->id]);
        $bystander = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $creator->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $bystander->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $creator->id, 'status' => KanbanTask::STATUS_REVIEW]);

        // Member board yang bukan assignee task ini tetap tidak boleh memundurkan status.
        Livewire::actingAs($bystander)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('moveTaskStatus', $task->id, KanbanTask::STATUS_IN_PROGRESS);
        $this->assertSame(KanbanTask::STATUS_REVIEW, $task->fresh()->status);
    }

    public function test_done_task_is_locked_from_status_and_checklist_changes_for_everyone(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $owner->id, 'status' => KanbanTask::STATUS_DONE]);
        $item = \App\Models\Kanban\KanbanTaskItem::create(['task_id' => $task->id, 'title' => 'Item lama', 'is_done' => false]);

        // Owner (bebas ubah status di kasus lain) tetap ditolak karena task sudah done.
        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('moveTaskStatus', $task->id, KanbanTask::STATUS_IN_PROGRESS);
        $this->assertSame(KanbanTask::STATUS_DONE, $task->fresh()->status);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('addItemToTask', $task->id, 'Item baru');
        $this->assertDatabaseMissing('kanban_task_items', ['title' => 'Item baru']);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('toggleItem', $item->id);
        $this->assertFalse($item->fresh()->is_done);

        // Komentar tetap boleh walau task sudah done.
        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('addCommentToTask', $task->id, 'Masih bisa komentar');
        $this->assertDatabaseHas('kanban_task_comments', ['task_id' => $task->id, 'comment' => 'Masih bisa komentar']);
    }

    public function test_task_creator_can_change_status_freely(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $creator = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $creator->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $creator->id]);

        Livewire::actingAs($creator)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('moveTaskStatus', $task->id, KanbanTask::STATUS_DONE);

        $this->assertSame(KanbanTask::STATUS_DONE, $task->fresh()->status);
    }

    public function test_add_comment_on_task(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('addCommentToTask', $task->id, 'Komentar dari test');

        $this->assertDatabaseHas('kanban_task_comments', [
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'comment' => 'Komentar dari test',
        ]);
    }

    public function test_any_board_member_can_comment_on_task(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $member->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $owner->id]);

        Livewire::actingAs($member)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('addCommentToTask', $task->id, 'Komentar dari member');

        $this->assertDatabaseHas('kanban_task_comments', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'comment' => 'Komentar dari member',
        ]);
    }

    public function test_checklist_item_add_toggle_and_delete(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $owner->id]);

        $component = Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('addItemToTask', $task->id, 'Sub-task 1');

        $item = \App\Models\Kanban\KanbanTaskItem::where('task_id', $task->id)->firstOrFail();
        $this->assertSame('Sub-task 1', $item->title);
        $this->assertFalse($item->is_done);

        $component->call('toggleItem', $item->id);
        $this->assertTrue($item->fresh()->is_done);

        $component->call('deleteItem', $item->id);
        $this->assertDatabaseMissing('kanban_task_items', ['id' => $item->id]);
    }

    public function test_task_member_can_add_item_but_unrelated_board_member_cannot(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $creator = User::factory()->create(['company_id' => $company->id]);
        $taskMember = User::factory()->create(['company_id' => $company->id]);
        $outsider = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $creator->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $taskMember->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $outsider->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $creator->id]);
        KanbanTaskMember::create(['task_id' => $task->id, 'user_id' => $taskMember->id]);

        Livewire::actingAs($taskMember)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('addItemToTask', $task->id, 'Item dari task member');

        $this->assertDatabaseHas('kanban_task_items', ['task_id' => $task->id, 'title' => 'Item dari task member']);

        Livewire::actingAs($outsider)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('addItemToTask', $task->id, 'Item dari orang luar')
            ->assertForbidden();

        $this->assertDatabaseMissing('kanban_task_items', ['title' => 'Item dari orang luar']);
    }

    public function test_only_owner_can_update_board_color(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $assigner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $assigner->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('updateColor', '#ff0000');

        $this->assertSame('#ff0000', $board->fresh()->color);

        Livewire::actingAs($assigner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('updateColor', '#00ff00')
            ->assertForbidden();

        $this->assertSame('#ff0000', $board->fresh()->color);
    }

    public function test_upload_attachment_on_task(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('openTask', $task->id)
            ->set('newAttachment', UploadedFile::fake()->image('bukti.png'))
            ->call('uploadAttachment');

        $this->assertDatabaseHas('kanban_task_attachments', [
            'task_id' => $task->id,
            'uploaded_by' => $owner->id,
            'file_name' => 'bukti.png',
        ]);
    }

    public function test_tasks_where_current_user_is_member_sort_first_in_column(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $otherMember = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $otherMember->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);

        $taskNotMine = KanbanTask::create(['board_id' => $board->id, 'title' => 'Bukan Task Owner', 'created_by' => $otherMember->id]);
        KanbanTaskMember::create(['task_id' => $taskNotMine->id, 'user_id' => $otherMember->id]);

        $taskMine = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task Owner', 'created_by' => $owner->id]);
        KanbanTaskMember::create(['task_id' => $taskMine->id, 'user_id' => $owner->id]);

        $tasksByStatus = Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->get('tasksByStatus');

        $titlesInTodo = collect($tasksByStatus[KanbanTask::STATUS_TODO])->pluck('title')->all();

        $this->assertSame(['Task Owner', 'Bukan Task Owner'], $titlesInTodo);
    }

    public function test_only_creator_or_owner_can_delete_task_from_component(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $assigner = User::factory()->create(['company_id' => $company->id]);
        $otherMember = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $assigner->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $otherMember->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $assigner->id]);

        // otherMember (bukan pembuat, bukan owner) DITOLAK.
        Livewire::actingAs($otherMember)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('deleteTask', $task->id)
            ->assertForbidden();
        $this->assertDatabaseHas('kanban_tasks', ['id' => $task->id, 'deleted_at' => null]);

        // owner boleh hapus task orang lain.
        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('deleteTask', $task->id);
        $this->assertSoftDeleted('kanban_tasks', ['id' => $task->id]);
    }

    public function test_owner_can_add_member_and_promote_to_assigner(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $newMember = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->set('newMemberUserId', $newMember->id)
            ->call('addMember');

        $this->assertDatabaseHas('kanban_board_members', [
            'board_id' => $board->id,
            'user_id' => $newMember->id,
            'role' => KanbanBoardMember::ROLE_MEMBER,
        ]);

        $memberRow = KanbanBoardMember::where('board_id', $board->id)->where('user_id', $newMember->id)->firstOrFail();

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('promoteToAssigner', $memberRow->id);

        $this->assertDatabaseHas('kanban_board_members', [
            'id' => $memberRow->id,
            'role' => KanbanBoardMember::ROLE_ASSIGNER,
        ]);
    }

    public function test_assigner_cannot_add_member_or_promote(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $assigner = User::factory()->create(['company_id' => $company->id]);
        $newMember = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $assigner->id, 'role' => KanbanBoardMember::ROLE_ASSIGNER]);

        Livewire::actingAs($assigner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->set('newMemberUserId', $newMember->id)
            ->call('addMember')
            ->assertForbidden();

        $this->assertDatabaseMissing('kanban_board_members', ['board_id' => $board->id, 'user_id' => $newMember->id]);
    }

    public function test_stranger_cannot_access_board_detail_page(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $stranger = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);

        $this->actingAs($stranger)
            ->get(route('kanban.show', ['board' => $board]))
            ->assertForbidden();
    }

    // ── KanbanPersonalTasks (Livewire) ─────────────────────────

    public function test_user_can_create_and_see_own_personal_task(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(KanbanPersonalTasks::class)
            ->set('title', 'Task Pribadi Saya')
            ->call('createTask');

        $this->assertDatabaseHas('kanban_tasks', [
            'board_id' => null,
            'title' => 'Task Pribadi Saya',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);
    }

    public function test_user_cannot_delete_others_personal_task(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $other = User::factory()->create(['company_id' => $company->id]);
        $task = KanbanTask::create(['board_id' => null, 'title' => 'Punya Owner', 'created_by' => $owner->id, 'assigned_to' => $owner->id]);

        // deleteTask scoped ke created_by = auth user, jadi task milik orang lain memang
        // tidak ketemu (findOrFail) — bukan abort_unless, makanya exception-nya beda dari
        // pola "Forbidden" di komponen lain.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($other)
            ->test(KanbanPersonalTasks::class)
            ->call('deleteTask', $task->id);

        $this->assertDatabaseHas('kanban_tasks', ['id' => $task->id, 'deleted_at' => null]);
    }

    public function test_personal_tasks_do_not_leak_into_board_view(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $user);

        KanbanTask::create(['board_id' => null, 'title' => 'Task Pribadi', 'created_by' => $user->id, 'assigned_to' => $user->id]);
        KanbanTask::create(['board_id' => $board->id, 'title' => 'Task Board', 'created_by' => $user->id, 'assigned_to' => $user->id]);

        $boardTaskTitles = collect(
            Livewire::actingAs($user)->test(KanbanBoardDetail::class, ['board' => $board])->get('tasksByStatus')
        )->flatten()->pluck('title');

        $this->assertContains('Task Board', $boardTaskTitles);
        $this->assertNotContains('Task Pribadi', $boardTaskTitles);
    }

    // ── Kanban Staff (KanbanBoardsList) ────────────────────────

    public function test_supervisor_can_assign_task_directly_to_subordinate(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor, 'staff' => $staff] = $this->makeSupervisorAndStaff($company);

        Livewire::actingAs($supervisor)
            ->test(KanbanBoardsList::class)
            ->set('staff_id', $staff->id)
            ->set('title', 'Rekap laporan bulanan')
            ->call('createStaffTask');

        $task = KanbanTask::where('title', 'Rekap laporan bulanan')->firstOrFail();
        $this->assertNull($task->board_id);
        $this->assertSame($supervisor->id, $task->created_by);
        $this->assertSame($staff->id, $task->assigned_to);
        $this->assertDatabaseHas('kanban_task_members', ['task_id' => $task->id, 'user_id' => $staff->id]);

        // Task itu otomatis muncul di Tugas Pribadi staff (query assigned_to = dia).
        $staffTaskTitles = collect(
            Livewire::actingAs($staff)->test(KanbanPersonalTasks::class)->get('tasksByStatus')
        )->flatten()->pluck('title');
        $this->assertContains('Rekap laporan bulanan', $staffTaskTitles);
    }

    public function test_staff_task_shows_on_kanban_list_page_for_both_sides_with_direct_actions(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor, 'staff' => $staff] = $this->makeSupervisorAndStaff($company);

        $task = KanbanTask::create([
            'board_id' => null,
            'title' => 'Titipan Lewat Kanban',
            'created_by' => $supervisor->id,
            'assigned_to' => $staff->id,
        ]);
        KanbanTaskMember::create(['task_id' => $task->id, 'user_id' => $staff->id]);

        $supervisorTasks = Livewire::actingAs($supervisor)->test(KanbanBoardsList::class)->get('staffTasks');
        $this->assertTrue(collect($supervisorTasks)->pluck('title')->contains('Titipan Lewat Kanban'));

        $staffTasks = Livewire::actingAs($staff)->test(KanbanBoardsList::class)->get('staffTasks');
        $this->assertTrue(collect($staffTasks)->pluck('title')->contains('Titipan Lewat Kanban'));

        // Staff bisa langsung ubah status dari /kanban.
        Livewire::actingAs($staff)
            ->test(KanbanBoardsList::class)
            ->call('moveStaffTaskStatus', $task->id, KanbanTask::STATUS_IN_PROGRESS);
        $this->assertSame(KanbanTask::STATUS_IN_PROGRESS, $task->fresh()->status);

        // Task hilang dari section setelah selesai.
        Livewire::actingAs($supervisor)
            ->test(KanbanBoardsList::class)
            ->call('moveStaffTaskStatus', $task->id, KanbanTask::STATUS_DONE);
        $tasksAfterDone = Livewire::actingAs($supervisor)->test(KanbanBoardsList::class)->get('staffTasks');
        $this->assertFalse(collect($tasksAfterDone)->pluck('title')->contains('Titipan Lewat Kanban'));
    }

    public function test_staff_cannot_skip_review_but_supervisor_can_pull_back_from_review(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor, 'staff' => $staff] = $this->makeSupervisorAndStaff($company);

        $task = KanbanTask::create([
            'board_id' => null,
            'title' => 'Titipan Bertahap',
            'created_by' => $supervisor->id,
            'assigned_to' => $staff->id,
            'status' => KanbanTask::STATUS_IN_PROGRESS,
        ]);
        KanbanTaskMember::create(['task_id' => $task->id, 'user_id' => $staff->id]);

        // Staff coba lompat langsung ke done, ditolak — harus lewat review dulu.
        Livewire::actingAs($staff)
            ->test(KanbanBoardsList::class)
            ->call('moveStaffTaskStatus', $task->id, KanbanTask::STATUS_DONE);
        $this->assertSame(KanbanTask::STATUS_IN_PROGRESS, $task->fresh()->status);

        // Staff maju 1 langkah ke review, diterima.
        Livewire::actingAs($staff)
            ->test(KanbanBoardsList::class)
            ->call('moveStaffTaskStatus', $task->id, KanbanTask::STATUS_REVIEW);
        $this->assertSame(KanbanTask::STATUS_REVIEW, $task->fresh()->status);

        // Supervisor (pembuat) bebas mengembalikan ke in_progress (minta revisi).
        Livewire::actingAs($supervisor)
            ->test(KanbanBoardsList::class)
            ->call('moveStaffTaskStatus', $task->id, KanbanTask::STATUS_IN_PROGRESS);
        $this->assertSame(KanbanTask::STATUS_IN_PROGRESS, $task->fresh()->status);
    }

    public function test_personal_task_stays_free_but_staff_assigned_task_gated_in_boardku(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor, 'staff' => $staff] = $this->makeSupervisorAndStaff($company);

        $ownTask = KanbanTask::create([
            'board_id' => null,
            'title' => 'Task Sendiri',
            'created_by' => $staff->id,
            'assigned_to' => $staff->id,
        ]);

        $delegatedTask = KanbanTask::create([
            'board_id' => null,
            'title' => 'Titipan Dari Atasan',
            'created_by' => $supervisor->id,
            'assigned_to' => $staff->id,
            'status' => KanbanTask::STATUS_IN_PROGRESS,
        ]);
        KanbanTaskMember::create(['task_id' => $delegatedTask->id, 'user_id' => $staff->id]);

        // Task pribadi murni tetap bebas lompat kemana saja.
        Livewire::actingAs($staff)
            ->test(KanbanPersonalTasks::class)
            ->call('moveTaskStatus', $ownTask->id, KanbanTask::STATUS_DONE);
        $this->assertSame(KanbanTask::STATUS_DONE, $ownTask->fresh()->status);

        // Task titipan dari atasan tidak boleh dilompat langsung ke done dari BoardKu staff.
        Livewire::actingAs($staff)
            ->test(KanbanPersonalTasks::class)
            ->call('moveTaskStatus', $delegatedTask->id, KanbanTask::STATUS_DONE);
        $this->assertSame(KanbanTask::STATUS_IN_PROGRESS, $delegatedTask->fresh()->status);
    }

    public function test_only_creator_can_delete_staff_task_from_kanban_list(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor, 'staff' => $staff] = $this->makeSupervisorAndStaff($company);

        $task = KanbanTask::create([
            'board_id' => null,
            'title' => 'Titipan Dihapus',
            'created_by' => $supervisor->id,
            'assigned_to' => $staff->id,
        ]);

        // deleteStaffTask scoped ke created_by = auth user (pola sama seperti
        // KanbanPersonalTasks::deleteTask) — staff (cuma assignee) dapat 404, bukan Forbidden.
        try {
            Livewire::actingAs($staff)
                ->test(KanbanBoardsList::class)
                ->call('deleteStaffTask', $task->id);
            $this->fail('Expected ModelNotFoundException');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // expected
        }
        $this->assertDatabaseHas('kanban_tasks', ['id' => $task->id]);

        Livewire::actingAs($supervisor)
            ->test(KanbanBoardsList::class)
            ->call('deleteStaffTask', $task->id);
        $this->assertSoftDeleted('kanban_tasks', ['id' => $task->id]);
    }

    public function test_done_staff_assigned_task_disappears_but_own_done_task_stays(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor, 'staff' => $staff] = $this->makeSupervisorAndStaff($company);

        $staffTask = KanbanTask::create([
            'board_id' => null,
            'title' => 'Titipan Selesai',
            'created_by' => $supervisor->id,
            'assigned_to' => $staff->id,
            'status' => KanbanTask::STATUS_DONE,
        ]);
        $ownTask = KanbanTask::create([
            'board_id' => null,
            'title' => 'Task Sendiri Selesai',
            'created_by' => $staff->id,
            'assigned_to' => $staff->id,
            'status' => KanbanTask::STATUS_DONE,
        ]);

        $titles = collect(
            Livewire::actingAs($staff)->test(KanbanPersonalTasks::class)->get('tasksByStatus')
        )->flatten()->pluck('title');

        $this->assertNotContains('Titipan Selesai', $titles);
        $this->assertContains('Task Sendiri Selesai', $titles);
    }

    public function test_supervisor_does_not_see_staff_assigned_task_on_own_boardku(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor, 'staff' => $staff] = $this->makeSupervisorAndStaff($company);

        KanbanTask::create([
            'board_id' => null,
            'title' => 'Ditugaskan Buat Staff',
            'created_by' => $supervisor->id,
            'assigned_to' => $staff->id,
            'status' => KanbanTask::STATUS_IN_PROGRESS,
        ]);

        // BoardKu supervisor cuma nampilin task yang assigned_to dia — task yang dia titipkan
        // ke staff tidak muncul di sini (tetap ada di section "Kanban Staff" /kanban).
        $supervisorTitles = collect(
            Livewire::actingAs($supervisor)->test(KanbanPersonalTasks::class)->get('tasksByStatus')
        )->flatten()->pluck('title');
        $this->assertNotContains('Ditugaskan Buat Staff', $supervisorTitles);

        $staffTitles = collect(
            Livewire::actingAs($staff)->test(KanbanPersonalTasks::class)->get('tasksByStatus')
        )->flatten()->pluck('title');
        $this->assertContains('Ditugaskan Buat Staff', $staffTitles);
    }

    public function test_staff_without_subordinates_cannot_open_staff_task_modal(): void
    {
        $company = $this->makeCompany();
        ['staff' => $staff] = $this->makeSupervisorAndStaff($company);

        Livewire::actingAs($staff)
            ->test(KanbanBoardsList::class)
            ->call('openStaffTaskModal')
            ->assertForbidden();
    }

    public function test_supervisor_cannot_assign_staff_task_to_non_subordinate(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor] = $this->makeSupervisorAndStaff($company);
        $stranger = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($supervisor)
            ->test(KanbanBoardsList::class)
            ->set('staff_id', $stranger->id)
            ->set('title', 'Task nyasar')
            ->call('createStaffTask')
            ->assertHasErrors('staff_id');

        $this->assertDatabaseMissing('kanban_tasks', ['title' => 'Task nyasar']);
    }
}
