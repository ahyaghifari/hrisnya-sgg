<?php

namespace Tests\Feature\Task;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Notifications\TaskMentioned;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Balasan komentar 1 tingkat + mention "@Nama". */
class TaskCommentReplyMentionTest extends TestCase
{
    use RefreshDatabase;

    private function board(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Company Test', 'location' => 'Test', 'is_active' => true]);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::BUAT_BOARD_TUGAS->value, 'guard_name' => 'web']);
        $owner = User::factory()->create(['company_id' => $company->id, 'name' => 'Owner Satu']);
        $owner->givePermissionTo(CustomPermissionEnum::BUAT_BOARD_TUGAS->value);
        $budi = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi']);
        $budiS = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        Auth::login($owner);

        $service = app(TaskService::class);
        $project = $service->createProject(['name' => 'B', 'member_ids' => [$budi->id, $budiS->id]]);
        $task = $service->createCasualTask(['requirement' => 'T', 'project_id' => $project->id, 'assigned_to' => $budi->id]);

        return [$service, $task, $owner, $budi, $budiS];
    }

    public function test_reply_is_one_level_only(): void
    {
        [$service, $task, $owner, $budi] = $this->board();

        $service->addComment($task, $owner, 'komentar awal');
        $root = $task->comments()->first();

        $service->addComment($task, $budi, 'balasan', $root->id);
        $reply = $task->comments()->whereNotNull('parent_id')->first();
        $this->assertSame($root->id, $reply->parent_id);

        try {
            $service->addComment($task, $owner, 'balas balasan', $reply->id);
            $this->fail('balasan dari balasan harusnya ditolak');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(2, $task->comments()->count());
    }

    public function test_mention_notifies_longest_name_only_and_not_self(): void
    {
        Notification::fake();
        [$service, $task, $owner, $budi, $budiS] = $this->board();

        $service->addComment($task, $owner, 'tolong cek @Budi Santoso dan @Owner Satu');

        Notification::assertSentTo($budiS, TaskMentioned::class);
        Notification::assertNotSentTo($budi, TaskMentioned::class); // "Budi" nggak ikut tertandai
        Notification::assertNotSentTo($owner, TaskMentioned::class);

        $html = (string) $task->comments()->first()->bodyHtml(['Budi', 'Budi Santoso']);
        $this->assertStringContainsString('@Budi Santoso</span>', $html);
    }

    public function test_nickname_is_the_mention_label_and_full_name_still_works(): void
    {
        Notification::fake();
        [$service, $task, $owner, $budi, $budiS] = $this->board();
        UserProfiles::forceCreate(['user_id' => $budiS->id, 'nickname' => 'Santo']);
        $budiS = $budiS->fresh('profile');

        $this->assertSame('Santo', $service->mentionLabel($budiS));
        $this->assertSame('Budi', $service->mentionLabel($budi));

        $service->addComment($task, $owner, 'cek @Santo');
        Notification::assertSentTo($budiS, TaskMentioned::class);

        $service->addComment($task, $owner, 'cek @Budi Santoso');
        Notification::assertSentToTimes($budiS, TaskMentioned::class, 2);
        Notification::assertNotSentTo($budi, TaskMentioned::class);
    }

    public function test_comment_body_is_escaped(): void
    {
        [$service, $task, $owner] = $this->board();
        $service->addComment($task, $owner, '<script>alert(1)</script> @Budi');

        $html = (string) $task->comments()->first()->bodyHtml(['Budi']);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('@Budi</span>', $html);
    }
}
