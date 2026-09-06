<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\ContractSubmission;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Dikirim ke karyawan saat penilai mengembalikan pengajuan perpanjangan kontraknya (status
 * balik ke draft) untuk melengkapi dokumen. Catatan alasan ada di decision_note.
 */
class ContractRevisionRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ContractSubmission $submission) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::CONTRACT_REVISION_REQUESTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function body(): string
    {
        $note = $this->submission->decision_note ? " Catatan: {$this->submission->decision_note}" : '';

        return "Penilai meminta perbaikan dokumen pengajuan perpanjangan kontrak Anda.{$note}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Dokumen Perpanjangan Kontrak Diminta Revisi',
            'body' => $this->body(),
            'contract_submission_id' => $this->submission->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Dokumen Perpanjangan Kontrak Diminta Revisi')
            ->body($this->body())
            ->data(['contract_submission_id' => $this->submission->id])
            ->action('Lihat', 'view');
    }
}
