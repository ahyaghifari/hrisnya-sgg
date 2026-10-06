<?php

namespace Tests\Feature\Task;

use App\Enum\CustomPermissionEnum;
use App\Enum\Task\TaskStatus;
use App\Models\Master\Companies;
use App\Models\Task\Task;
use App\Models\User;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskRecurringTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_period_stays_done_until_period_ends_then_same_task_resumes(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
        $owner = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate(CustomPermissionEnum::BUAT_BOARD_TUGAS->value, 'web');
        $owner->givePermissionTo(CustomPermissionEnum::BUAT_BOARD_TUGAS->value);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board R']);
        $due = now()->addDays(5)->setTime(23, 59);

        $task = $service->createCasualTask([
            'requirement' => 'Laporan bulanan',
            'project_id' => $project->id,
            'due_date' => $due->format('Y-m-d H:i:s'),
            'recurrence' => 'monthly',
            'checklist' => ['Cek data'],
        ]);
        $originListId = $task->list_id;
        $task->checklistItems->first()->update(['is_done' => true]);
        $task->comments()->create(['user_id' => $owner->id, 'comment' => 'catatan periode 1']);

        $same = $service->completeRecurring($task, $owner);

        // Periode ini selesai: tetap Selesai (bukan Todo baru), checklist masih tercentang.
        $task->refresh();
        $this->assertSame($task->id, $same->id);
        $this->assertSame(1, Task::where('requirement', 'Laporan bulanan')->count());
        $this->assertSame(TaskStatus::COMPLETE->value, $task->status);
        $this->assertSame('monthly', $task->recurrence);
        $this->assertSame($originListId, $task->list_id);
        $this->assertTrue((bool) $task->checklistItems()->first()->is_done);
        $this->assertSame($due->copy()->startOfDay()->addDay()->toDateTimeString(), $task->recurrence_resume_at->toDateTimeString());
        $this->assertSame($due->copy()->addMonthNoOverflow()->toDateString(), \Illuminate\Support\Carbon::parse($task->due_date)->toDateString());

        // Periodenya belum lewat: job tidak mengaktifkan lagi.
        $this->assertSame(0, $service->resumeDueRecurring());
        $this->assertSame(TaskStatus::COMPLETE->value, $task->fresh()->status);

        // Periode lewat: tugas yang sama aktif lagi dengan tenggat berikutnya, checklist di-unceklis.
        \Illuminate\Support\Carbon::setTestNow($task->recurrence_resume_at->copy()->addHour());
        $this->assertSame(1, $service->resumeDueRecurring());

        $task->refresh();
        $this->assertSame(TaskStatus::TODO->value, $task->status);
        $this->assertNull($task->recurrence_resume_at);
        $this->assertSame($due->copy()->addMonthNoOverflow()->toDateString(), \Illuminate\Support\Carbon::parse($task->due_date)->toDateString());
        $this->assertFalse((bool) $task->checklistItems()->first()->is_done);
        $this->assertSame(1, $task->comments()->count());

        $log = $task->activities()->where('action', 'recurred')->first();
        $this->assertSame(['Cek data'], $log->meta['checklist_done']);
    }

    private function boardWithRecurringTask(array $extra = []): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
        $owner = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate(CustomPermissionEnum::BUAT_BOARD_TUGAS->value, 'web');
        $owner->givePermissionTo(CustomPermissionEnum::BUAT_BOARD_TUGAS->value);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board R']);
        $task = $service->createCasualTask([
            'requirement' => 'Laporan mingguan',
            'project_id' => $project->id,
            'due_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'recurrence' => 'weekly',
            ...$extra,
        ]);

        return [$service, $owner, $project, $task];
    }

    public function test_checklist_item_due_date_can_be_set_and_cleared(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
        $owner = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate(CustomPermissionEnum::BUAT_BOARD_TUGAS->value, 'web');
        $owner->givePermissionTo(CustomPermissionEnum::BUAT_BOARD_TUGAS->value);
        Auth::login($owner);
        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'Board C']);
        $task = $service->createCasualTask(['requirement' => 'Tugas', 'project_id' => $project->id, 'checklist' => ['Item']]);
        $item = $task->checklistItems->first();
        $service->setChecklistItemDueDate($item, '2026-12-01T10:00', $owner);
        $this->assertSame('2026-12-01 10:00', $item->fresh()->due_date->format('Y-m-d H:i'));
        $service->setChecklistItemDueDate($item, null, $owner);
        $this->assertNull($item->fresh()->due_date);
    }

    public function test_recurring_task_works_without_final_list(): void
    {
        [$service, $owner, $project, $task] = $this->boardWithRecurringTask();
        $project->lists()->where('is_final', true)->delete();

        $service->completeRecurring($task, $owner);

        $this->assertSame(TaskStatus::COMPLETE->value, $task->fresh()->status);
    }

    public function test_recurring_task_needing_review_must_go_through_review_list(): void
    {
        [$service, $owner, $project, $task] = $this->boardWithRecurringTask();
        // Satu List Review di Board, dan kartu ada di List kerja (bukan di List Review itu).
        $project->lists()->update(['is_review' => false]);
        $project->lists()->where('is_final', false)->where('id', '!=', $task->list_id)->first()->update(['is_review' => true]);
        $task->update(['needs_review' => true]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('wajib direview');

        $service->completeRecurring($task->fresh(), $owner);
    }
}
