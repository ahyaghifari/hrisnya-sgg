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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

    public function test_casual_task_reviewer_ids_are_assigned_manually(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $peer = User::factory()->create(['company_id' => $company->id]);
        $reviewerA = User::factory()->create(['company_id' => $company->id]);
        $reviewerB = User::factory()->create(['company_id' => $company->id]);
        Auth::login($creator);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Project E', 'member_ids' => [$peer->id]]);

        $forOther = $service->createCasualTask([
            'requirement' => 'Kerjaan buat teman project',
            'assigned_to' => $peer->id,
            'project_id' => $project->id,
            'reviewer_ids' => [$reviewerA->id, $reviewerB->id],
        ]);
        $this->assertCount(2, $forOther->reviews);
        $this->assertTrue($forOther->reviews->every(fn ($row) => $row->status === 'pending'));

        $forSelf = $service->createCasualTask(['requirement' => 'Kerjaan sendiri']);
        $this->assertCount(0, $forSelf->reviews);
    }

    public function test_move_status_blocks_review_to_complete_when_reviewer_required(): void
    {
        $company = $this->makeCompany();
        $reviewer = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00005',
            'company_id' => $company->id,
            'created_by' => $reviewer->id,
            'assigned_to' => $assignee->id,
            'status' => TaskStatus::REVIEW->value,
        ]);
        $task->reviews()->create(['reviewer_id' => $reviewer->id, 'status' => 'pending']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->moveStatus($task, TaskStatus::COMPLETE->value, $assignee);
    }

    public function test_approve_review_completes_task_only_when_all_reviewers_approved(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);
        $reviewerA = User::factory()->create(['company_id' => $company->id]);
        $reviewerB = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00030', 'company_id' => $company->id,
            'created_by' => $creator->id, 'assigned_to' => $assignee->id,
            'status' => TaskStatus::REVIEW->value,
        ]);
        $task->reviews()->create(['reviewer_id' => $reviewerA->id, 'status' => 'pending']);
        $task->reviews()->create(['reviewer_id' => $reviewerB->id, 'status' => 'pending']);

        $service = app(TaskService::class);

        $service->approveReview($task, $reviewerA, 5, 'Mantap');
        $this->assertSame(TaskStatus::REVIEW->value, $task->fresh()->status);

        $service->approveReview($task, $reviewerB, 4, null);
        $this->assertSame(TaskStatus::COMPLETE->value, $task->fresh()->status);
    }

    public function test_reject_review_keeps_other_approved_reviewer_decision(): void
    {
        $company = $this->makeCompany();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);
        $reviewerA = User::factory()->create(['company_id' => $company->id]);
        $reviewerB = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00031', 'company_id' => $company->id,
            'created_by' => $creator->id, 'assigned_to' => $assignee->id,
            'status' => TaskStatus::REVIEW->value,
        ]);
        $task->reviews()->create(['reviewer_id' => $reviewerA->id, 'status' => 'pending']);
        $task->reviews()->create(['reviewer_id' => $reviewerB->id, 'status' => 'pending']);

        $service = app(TaskService::class);
        $service->approveReview($task, $reviewerA, 5, null);
        $service->rejectReview($task, $reviewerB, 'Belum sesuai spesifikasi.');

        $this->assertSame(TaskStatus::IN_PROGRESS->value, $task->fresh()->status);
        $this->assertSame('approved', $task->reviews()->where('reviewer_id', $reviewerA->id)->first()->status);
        $this->assertSame('rejected', $task->reviews()->where('reviewer_id', $reviewerB->id)->first()->status);

        // Disubmit ulang ke tinjauan — baris yang rejected balik pending, yang approved tetap.
        $service->moveStatus($task->fresh(), TaskStatus::REVIEW->value, $assignee);

        $this->assertSame('approved', $task->reviews()->where('reviewer_id', $reviewerA->id)->first()->status);
        $this->assertSame('pending', $task->reviews()->where('reviewer_id', $reviewerB->id)->first()->status);

        // Reviewer yang tadi reject sekarang approve — task langsung Complete, reviewer A tidak perlu vote ulang.
        $service->approveReview($task->fresh(), $reviewerB, 4, null);
        $this->assertSame(TaskStatus::COMPLETE->value, $task->fresh()->status);
    }

    public function test_review_task_sets_status_complete(): void
    {
        $company = $this->makeCompany();
        $reviewer = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00006',
            'company_id' => $company->id,
            'created_by' => $reviewer->id,
            'assigned_to' => $assignee->id,
            'sla_reviewer_id' => $reviewer->id,
            'status' => TaskStatus::REVIEW->value,
        ]);

        Auth::login($reviewer);
        app(TaskService::class)->reviewTask($task->id, (object) ['rating' => 5, 'comment' => 'Mantap']);

        $this->assertSame(TaskStatus::COMPLETE->value, $task->fresh()->status);
        $this->assertDatabaseHas('task_reviews', [
            'task_id' => $task->id,
            'reviewer_id' => $reviewer->id,
            'rating' => 5,
        ]);
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

    public function test_riwayat_scope_filters_by_1_status_yang_dipilih(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        Auth::login($user);

        Task::create([
            'code' => 'TSK00010', 'company_id' => $company->id,
            'created_by' => $user->id, 'assigned_to' => $user->id,
            'status' => TaskStatus::TODO->value,
        ]);
        $complete = Task::create([
            'code' => 'TSK00011', 'company_id' => $company->id,
            'created_by' => $user->id, 'assigned_to' => $user->id,
            'status' => TaskStatus::COMPLETE->value,
        ]);
        $rejected = Task::create([
            'code' => 'TSK00012', 'company_id' => $company->id,
            'created_by' => $user->id, 'assigned_to' => $user->id,
            'status' => TaskStatus::REJECTED->value,
        ]);

        $service = app(TaskService::class);

        $selesai = $service->getPortalTasks($user, 'riwayat', TaskStatus::COMPLETE->value);
        $this->assertEqualsCanonicalizing([$complete->id], $selesai->pluck('id')->all());

        $ditolak = $service->getPortalTasks($user, 'riwayat', TaskStatus::REJECTED->value);
        $this->assertEqualsCanonicalizing([$rejected->id], $ditolak->pluck('id')->all());
    }

    public function test_reject_review_returns_task_to_in_progress_with_private_note(): void
    {
        $company = $this->makeCompany();
        $reviewer = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00020', 'company_id' => $company->id,
            'created_by' => $reviewer->id, 'assigned_to' => $assignee->id,
            'sla_reviewer_id' => $reviewer->id,
            'status' => TaskStatus::REVIEW->value,
        ]);

        app(TaskService::class)->rejectReview($task, $reviewer, 'Datanya belum lengkap, tolong dicek lagi.');

        $fresh = $task->fresh();
        $this->assertSame(TaskStatus::IN_PROGRESS->value, $fresh->status);
        $this->assertSame('Datanya belum lengkap, tolong dicek lagi.', $fresh->rejected_reason);
        $this->assertSame($reviewer->id, $fresh->rejection_note_by);
        $this->assertNotNull($fresh->rejection_note_at);
    }

    public function test_reject_review_forbidden_for_non_reviewer_non_creator(): void
    {
        $company = $this->makeCompany();
        $reviewer = User::factory()->create(['company_id' => $company->id]);
        $assignee = User::factory()->create(['company_id' => $company->id]);
        $stranger = User::factory()->create(['company_id' => $company->id]);

        $task = Task::create([
            'code' => 'TSK00021', 'company_id' => $company->id,
            'created_by' => $reviewer->id, 'assigned_to' => $assignee->id,
            'sla_reviewer_id' => $reviewer->id,
            'status' => TaskStatus::REVIEW->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TaskService::class)->rejectReview($task, $stranger, 'Coba tolak punya orang lain.');
    }
}
