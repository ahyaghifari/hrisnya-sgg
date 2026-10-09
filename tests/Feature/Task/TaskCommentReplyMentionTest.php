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

    public function test_board_discussion_reply_one_level_and_mention(): void
    {
        [$service, $task, $owner, $budi, $budiS] = $this->board();
        $project = $task->project;
        Notification::fake();

        $service->addProjectComment($project, $owner, 'halo @Budi Santoso');
        $root = $project->comments()->first();
        Notification::assertSentTo($budiS, TaskMentioned::class);
        Notification::assertNotSentTo($budi, TaskMentioned::class);

        $service->addProjectComment($project, $budiS, 'siap', $root->id);
        $reply = $project->comments()->whereNotNull('parent_id')->first();

        try {
            $service->addProjectComment($project, $owner, 'balas balasan', $reply->id);
            $this->fail('balasan dari balasan harusnya ditolak');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_board_discussion_unread_counts_all_messages_from_others_until_seen(): void
    {
        [$service, $task, $owner, $budi, $budiS] = $this->board();
        $project = $task->project;
        Notification::fake();

        $service->addProjectComment($project, $owner, 'halo @Budi Santoso');
        $service->addProjectComment($project, $owner, 'pesan umum tanpa tag');
        $this->assertSame(2, $service->unreadDiscussionCount($project, $budiS));
        $this->assertSame(2, $service->unreadDiscussionCount($project, $budi));

        $service->markDiscussionSeen($project, $budiS);
        $this->assertSame(0, $service->unreadDiscussionCount($project, $budiS));

        $this->travel(5)->seconds();
        $service->addProjectComment($project, $budiS, 'pesanku');
        $mine = $project->comments()->where('user_id', $budiS->id)->first();
        $service->addProjectComment($project, $owner, 'balasan', $mine->id);
        $this->assertSame(1, $service->unreadDiscussionCount($project, $budiS));
    }

    public function test_notification_context_and_links_for_card_and_discussion(): void
    {
        [$service, $task, $owner, $budi] = $this->board();
        $project = $task->project;

        $card = (new TaskMentioned($task, 'Owner Satu', 'cek ini'))->toDatabase($budi);
        $this->assertStringContainsString('Board "B"', $card['context']);
        $this->assertStringContainsString('Kartu "T"', $card['context']);
        $this->assertStringContainsString('task=' . $task->id, \App\Support\TaskNotificationUrl::for($card));

        $disc = (new TaskMentioned(null, 'Owner Satu', 'cek ini', $project))->toDatabase($budi);
        $this->assertSame('Board "B" · bagian Diskusi', $disc['context']);
        $this->assertTrue($disc['discussion']);
        $this->assertStringContainsString('boardFilter=diskusi', \App\Support\TaskNotificationUrl::for($disc));
    }

    public function test_comment_with_file_is_attached_to_comment_and_listed_in_attachments(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        [$service, $task, $owner, $budi] = $this->board();
        Notification::fake();

        // Anggota Board yang bukan pelaksana/pembuat tetap boleh melampirkan lewat komentar.
        $service->addComment($task, $budi, '', null, \Illuminate\Http\UploadedFile::fake()->create('laporan.docx', 20));

        $comment = $task->comments()->first();
        $this->assertSame('', $comment->comment);
        $attachment = $task->attachments()->first();
        $this->assertSame($comment->id, $attachment->comment_id);
        $this->assertSame('laporan.docx', $attachment->file_name);
        $this->assertSame(1, $comment->attachments()->count());

        try {
            $service->addComment($task, $budi, 'pdf besar', null, \Illuminate\Http\UploadedFile::fake()->create('besar.pdf', 3000, 'application/pdf'));
            $this->fail('PDF > 2 MB harusnya ditolak');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(1, $task->comments()->count());
    }

    public function test_members_can_attach_only_when_card_setting_enabled(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        [$service, $task, $owner, $budi, $budiS] = $this->board(); // $budi pelaksana, $budiS anggota biasa
        $file = fn () => \Illuminate\Http\UploadedFile::fake()->create('a.docx', 10);

        try {
            $service->addFileAttachment($task, $budiS, $file());
            $this->fail('anggota biasa harusnya ditolak saat pengaturan mati');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // Cuma pembuat/owner yang boleh mengubah pengaturan.
        try {
            $service->setMembersCanAttach($task, true, $budiS);
            $this->fail('anggota biasa tidak boleh mengubah pengaturan');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $service->setMembersCanAttach($task, true, $owner);
        $service->addFileAttachment($task->fresh(), $budiS, $file());
        $this->assertSame(1, $task->attachments()->count());

        // Anggota biasa hanya bisa menghapus lampiran miliknya sendiri.
        $service->addFileAttachment($task->fresh(), $owner, $file());
        $ownerAtt = $task->attachments()->where('uploaded_by', $owner->id)->first();
        try {
            $service->removeAttachment($ownerAtt->id, $budiS);
            $this->fail('tidak boleh menghapus lampiran orang lain');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $own = $task->attachments()->where('uploaded_by', $budiS->id)->first();
        $service->removeAttachment($own->id, $budiS);
        $this->assertSame(1, $task->attachments()->count());
    }
}
