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
 * Dikirim ke karyawan orientasi saat penilai mengembalikan pengajuannya (status balik ke draft)
 * untuk perbaikan bukti. Catatan alasan ada di decision_note.
 */
class OrientationRevisionRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected OrientationSubmission $submission) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ORIENTATION_REVISION_REQUESTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function body(): string
    {
        $note = $this->submission->decision_note ? " Catatan: {$this->submission->decision_note}" : '';

        return "Penilai meminta perbaikan bukti orientasi Anda.{$note}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Bukti Orientasi Diminta Revisi',
            'body' => $this->body(),
            'orientation_submission_id' => $this->submission->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Bukti Orientasi Diminta Revisi')
            ->body($this->body())
            ->data(['orientation_submission_id' => $this->submission->id])
            ->action('Lihat', 'view');
    }
}
