<?php

namespace Tests\Feature\Task;

use App\Enum\Task\TaskStatus;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TaskRecurringTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_recurring_card_moves_it_to_final_list_and_clones_with_next_due_date(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
        $owner = User::factory()->create(['company_id' => $company->id]);
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

        $copy = $service->completeRecurring($task, $owner);

        $task->refresh();
        $this->assertSame(TaskStatus::COMPLETE->value, $task->status);
        $this->assertNull($task->recurrence);

        $this->assertNotSame($task->id, $copy->id);
        $this->assertSame(TaskStatus::TODO->value, $copy->status);
        $this->assertSame('monthly', $copy->recurrence);
        $this->assertSame($task->list_id === $copy->list_id, false); // lama di List Selesai, salinan di List asal
        $this->assertSame($due->copy()->addMonthNoOverflow()->toDateString(), \Illuminate\Support\Carbon::parse($copy->due_date)->toDateString());
        $this->assertFalse((bool) $copy->checklistItems->first()->is_done);
    }
}
