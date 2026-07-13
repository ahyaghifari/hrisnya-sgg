<?php

namespace Tests\Feature;

use App\Livewire\Kanban\CreateKanbanBoard;
use App\Livewire\Kanban\KanbanBoardDetail;
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

    public function test_can_create_board_requires_subordinates_or_global_access(): void
    {
        $company = $this->makeCompany();
        ['supervisor' => $supervisor, 'staff' => $staff] = $this->makeSupervisorAndStaff($company);
        $service = app(KanbanAuthorizationService::class);

        $this->assertTrue($service->canCreateBoard($supervisor));
        $this->assertFalse($service->canCreateBoard($staff));
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

    public function test_staff_without_subordinates_cannot_open_create_board_page(): void
    {
        $company = $this->makeCompany();
        ['staff' => $staff] = $this->makeSupervisorAndStaff($company);

        $this->actingAs($staff)
            ->get(route('kanban.create'))
            ->assertForbidden();
    }

    // ── KanbanBoardDetail (Livewire) ───────────────────────────

    public function test_member_creating_task_defaults_to_self_assigned(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $member->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);

        Livewire::actingAs($member)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->set('title', 'Task oleh member')
            ->call('createTask');

        $this->assertDatabaseHas('kanban_tasks', [
            'board_id' => $board->id,
            'title' => 'Task oleh member',
            'assigned_to' => $member->id,
        ]);
    }

    public function test_member_cannot_assign_task_to_someone_else(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $member = User::factory()->create(['company_id' => $company->id]);
        $anotherMember = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $member->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);
        KanbanBoardMember::create(['board_id' => $board->id, 'user_id' => $anotherMember->id, 'role' => KanbanBoardMember::ROLE_MEMBER]);

        Livewire::actingAs($member)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->set('title', 'Coba assign ke orang lain')
            ->set('assigned_to', $anotherMember->id)
            ->call('createTask');

        $this->assertDatabaseMissing('kanban_tasks', ['title' => 'Coba assign ke orang lain']);
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
            ->set('assigned_to', $member->id)
            ->call('createTask');

        $task = KanbanTask::where('title', 'Task buat member')->firstOrFail();
        $this->assertSame($member->id, $task->assigned_to);
        // Konsistensi assigned_to <-> kanban_task_members (dikonfirmasi wajib di knowledge doc).
        $this->assertDatabaseHas('kanban_task_members', ['task_id' => $task->id, 'user_id' => $member->id]);
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

    public function test_add_comment_on_task(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $board = $this->makeBoardWithOwner($company, $owner);
        $task = KanbanTask::create(['board_id' => $board->id, 'title' => 'Task', 'created_by' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(KanbanBoardDetail::class, ['board' => $board])
            ->call('openTask', $task->id)
            ->set('newComment', 'Komentar dari test')
            ->call('addComment');

        $this->assertDatabaseHas('kanban_task_comments', [
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'comment' => 'Komentar dari test',
        ]);
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
}
