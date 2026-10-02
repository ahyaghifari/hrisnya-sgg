<?php

namespace Tests\Feature\Task;

use App\Enum\CustomPermissionEnum;
use App\Enum\Task\TaskStatus;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** 1 kartu Board bisa dikerjakan 2 orang, dinilai sekaligus atau satu per satu. */
class TaskCoAssigneeTest extends TestCase
{
    use RefreshDatabase;

    private function setupBoardTask(array $extra = []): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
        $creator = User::factory()->create(['company_id' => $company->id]);
        $a = User::factory()->create(['company_id' => $company->id]);
        $b = User::factory()->create(['company_id' => $company->id]);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::BUAT_BOARD_TUGAS->value, 'guard_name' => 'web']);
        $creator->givePermissionTo(CustomPermissionEnum::BUAT_BOARD_TUGAS->value);
        Auth::login($creator);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board', 'member_ids' => [$a->id, $b->id]]);
        $task = $service->createCasualTask([
            'requirement' => 'Kerjaan bareng',
            'assigned_to' => $a->id,
            'project_id' => $project->id,
            'needs_review' => false,
            'reviewer_id' => $creator->id,
        ] + $extra);
        $service->addAssignee($task, $b->id, $creator);

        return [$service, $task->fresh(), $creator, $a, $b];
    }

    public function test_card_has_two_assignees_and_both_can_move_it(): void
    {
        [$service, $task, , $a, $b] = $this->setupBoardTask();

        $this->assertSame($a->id, $task->assigned_to);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $task->assignees->pluck('user_id')->all());
        $this->assertFalse((bool) $task->is_self_assigned);

        $service->moveStatus($task->fresh(), TaskStatus::IN_PROGRESS->value, $b);
        $this->assertSame(TaskStatus::IN_PROGRESS->value, $task->fresh()->status);
    }

    public function test_add_assignee_allows_more_than_two_but_rejects_outsider_duplicate_and_non_assigner(): void
    {
        [$service, $task, $creator, $a, $b] = $this->setupBoardTask();
        $third = User::factory()->create(['company_id' => $creator->company_id]);
        $outsider = User::factory()->create(['company_id' => $creator->company_id]);
        $service->addProjectMember($task->project, $third->id, $creator);

        $service->addAssignee($task, $third->id, $creator);
        $this->assertCount(3, $task->assignees()->get());

        $cases = [
            'sudah jadi pelaksana' => [$b->id, $creator, 422],
            'bukan anggota Board' => [$outsider->id, $creator, 403],
            'bukan Assigner' => [$outsider->id, $a, 403],
        ];
        foreach ($cases as $label => [$userId, $actor, $code]) {
            try {
                $service->addAssignee($task, $userId, $actor);
                $this->fail("harusnya ditolak: $label");
            } catch (HttpException $e) {
                $this->assertSame($code, $e->getStatusCode(), $label);
            }
        }

        $this->assertCount(3, $task->assignees()->get());
    }

    public function test_rating_one_by_one_then_all_and_creator_rating_mirrors_average(): void
    {
        [$service, $task, $creator, $a, $b] = $this->setupBoardTask();
        $task->update(['status' => TaskStatus::COMPLETE->value]);
        $task = $task->fresh();

        $this->assertTrue($task->requiresRatingFrom($creator->id));

        $service->rateTask($task, 5, $creator, $a->id);
        $task = $task->fresh();
        $this->assertTrue($task->requiresRatingFrom($creator->id), 'B belum dinilai');
        $this->assertSame(5, $task->rating);

        $service->rateTask($task, 3, $creator); // semua yang belum dinilai = cuma B
        $task = $task->fresh();
        $this->assertFalse($task->requiresRatingFrom($creator->id));
        $this->assertSame(5, $task->assignees->firstWhere('user_id', $a->id)->rating);
        $this->assertSame(3, $task->assignees->firstWhere('user_id', $b->id)->rating);
        $this->assertSame(4, $task->rating);
    }

    public function test_rating_both_at_once_and_assignee_cannot_rate(): void
    {
        [$service, $task, $creator, $a] = $this->setupBoardTask();
        $task->update(['status' => TaskStatus::COMPLETE->value]);

        try {
            $service->rateTask($task->fresh(), 5, $a);
            $this->fail('pelaksana bukan penilai');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $service->rateTask($task->fresh(), 4, $creator);
        $this->assertSame([4, 4], $task->fresh()->assignees->pluck('rating')->all());
    }
}
