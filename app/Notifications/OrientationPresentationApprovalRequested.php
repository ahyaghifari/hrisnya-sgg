<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\OrientationSubmission;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Dikirim ke pemegang jabatan pendukung saat HR sudah menetapkan jadwal presentasi kegiatan
 * orientasi seorang karyawan dan menunggu persetujuannya.
 */
class OrientationPresentationApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected OrientationSubmission $submission) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ORIENTATION_PRESENTATION_APPROVAL_REQUESTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function body(): string
    {
        $name = $this->submission->user?->name ?? 'Karyawan';
        $at = ($this->submission->presentation_confirmed_at ?? $this->submission->presentation_proposed_at)?->translatedFormat('l, d F Y H:i');

        return "Jadwal presentasi kegiatan orientasi {$name} ({$at}) menunggu persetujuan Anda.";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Persetujuan Jadwal Presentasi Orientasi',
            'body' => $this->body(),
            'orientation_submission_id' => $this->submission->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Persetujuan Jadwal Presentasi Orientasi')
            ->body($this->body())
            ->data(['orientation_submission_id' => $this->submission->id])
            ->action('Lihat', 'view');
    }
}
