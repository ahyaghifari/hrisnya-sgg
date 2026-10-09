<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Task\Task;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TaskReviewRequested extends Notification implements ShouldQueue
{
    use Queueable, \App\Notifications\Concerns\DescribesTaskContext;

    public function __construct(protected Task $task, protected string $assigneeName) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::TASK_REVIEW_REQUESTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Pengajuan Review Tugas';
    }

    protected function body(): string
    {
        return "{$this->assigneeName} mengajukan review: " . Str::limit($this->task->requirement, 80);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'context' => $this->context(),
            'request_type' => 'task',
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body() . "\n" . $this->context())
            ->data(['request_type' => 'task', 'task_id' => $this->task->id, 'project_id' => $this->task->project_id])
            ->action('Lihat', 'view');
    }
}
