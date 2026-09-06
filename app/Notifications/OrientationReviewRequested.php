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
 * Dikirim ke penilai (resolve dari sikartap_reviewers, context `orientation`) saat karyawan
 * orientasi mengajukan penilaian. Pengganti notifikasi "menunggu persetujuan" generik dari
 * Approval Engine yang sudah dilepas.
 */
class OrientationReviewRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected OrientationSubmission $submission) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ORIENTATION_REVIEW_REQUESTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function body(): string
    {
        $name = $this->submission->user?->name ?? 'Karyawan';

        return "{$name} mengajukan penilaian orientasi. Mohon dinilai.";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Pengajuan Orientasi Perlu Dinilai',
            'body' => $this->body(),
            'orientation_submission_id' => $this->submission->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Pengajuan Orientasi Perlu Dinilai')
            ->body($this->body())
            ->data(['orientation_submission_id' => $this->submission->id])
            ->action('Lihat', 'view');
    }
}
