<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Task\TaskProject;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TaskProjectMemberAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected TaskProject $project, protected string $addedByName) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::TASK_PROJECT_MEMBER_ADDED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Ditambahkan ke Project';
    }

    protected function body(): string
    {
        return "{$this->addedByName} menambahkan Anda ke project \"{$this->project->name}\"";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'project_id' => $this->project->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['project_id' => $this->project->id])
            ->action('Lihat', 'view');
    }
}
