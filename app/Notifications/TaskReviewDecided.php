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

/** Ke pelaksana, saat creator mutusin task yang di-REVIEW: approve (ke COMPLETE) atau kembalikan (ke IN_PROGRESS). */
class TaskReviewDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Task $task, protected bool $approved, protected string $deciderName) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::TASK_REVIEW_DECIDED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return $this->approved ? '🎉 Tugas Disetujui!' : 'Tugas Dikembalikan';
    }

    protected function body(): string
    {
        $name = Str::limit($this->task->requirement, 60);

        return $this->approved
            ? "Mantap! \"{$name}\" disetujui {$this->deciderName} dan sudah resmi selesai. Kerja bagus! 🎊✨"
            : "{$this->deciderName} mengembalikan \"{$name}\" buat direvisi lagi.";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
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
            ->body($this->body())
            ->data(['request_type' => 'task', 'task_id' => $this->task->id, 'project_id' => $this->task->project_id])
            ->action('Lihat', 'view');
    }
}
