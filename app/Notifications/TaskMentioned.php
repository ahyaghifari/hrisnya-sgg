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

class TaskMentioned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ?Task $task, protected string $commenterName, protected string $comment, protected ?string $projectId = null) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::TASK_MENTIONED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Anda Disebut di Komentar';
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
            'request_type' => 'task',
            'task_id' => $this->task?->id,
            'project_id' => $this->task?->project_id ?? $this->projectId,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['request_type' => 'task', 'task_id' => $this->task?->id, 'project_id' => $this->task?->project_id ?? $this->projectId])
            ->action('Lihat', 'view');
    }
}
