<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Task\Task;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Ke pelaksana tugas (assigned_to), saat reviewer approve/reject tugasnya — lihat
 * TaskService::approveReview()/rejectReview(). */
class TaskReviewDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Task $task, protected string $reviewerName, protected bool $approved) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::TASK_REVIEW_DECIDED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return $this->approved ? 'Tugas Disetujui' : 'Tugas Ditolak Reviewer';
    }

    protected function body(): string
    {
        return $this->approved
            ? "{$this->reviewerName} menyetujui tugas {$this->task->code}"
            : "{$this->reviewerName} menolak tugas {$this->task->code}, perlu direvisi";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'task_id' => $this->task->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['task_id' => $this->task->id])
            ->action('Lihat', 'view');
    }
}
