<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Enum\OrientationDecisionEnum;
use App\Models\OrientationSubmission;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Dikirim ke karyawan orientasi saat pengajuan penilaian orientasinya sudah diputuskan penilai
 * (direkomendasikan untuk kontrak kerja / tidak dilanjutkan). Membawa isi keputusannya.
 */
class OrientationReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected OrientationSubmission $submission) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ORIENTATION_REVIEWED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function decisionLabel(): string
    {
        return $this->submission->decision
            ? OrientationDecisionEnum::from($this->submission->decision)->getLabel()
            : 'Selesai dinilai';
    }

    protected function body(): string
    {
        $note = $this->submission->decision_note ? " Catatan: {$this->submission->decision_note}" : '';

        return "Hasil penilaian orientasi Anda: {$this->decisionLabel()}.{$note}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Penilaian Orientasi Selesai',
            'body' => $this->body(),
            'orientation_submission_id' => $this->submission->id,
            'decision' => $this->submission->decision,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Penilaian Orientasi Selesai')
            ->body($this->body())
            ->data(['orientation_submission_id' => $this->submission->id])
            ->action('Lihat', 'view');
    }
}
