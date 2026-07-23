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

class TaskCommented extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Task $task, protected string $commenterName, protected string $comment) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::TASK_COMMENTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Komentar Baru di Tugas';
    }

    protected function body(): string
    {
        return "{$this->commenterName}: " . Str::limit($this->comment, 80);
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
