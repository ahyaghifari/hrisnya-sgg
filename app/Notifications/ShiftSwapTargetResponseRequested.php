<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Shift\ShiftSwapRequests;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Ke rekan (target_id) yang jadwalnya mau ditukar - opsional, cuma dikirim kalau setting
 * ask_target_response_tukar_shift ON (lihat App\Services\Shift\ShiftSwapRequestService). Target
 * boleh kasih tanggapan lewat App\Livewire\Presensi\ShiftSwapTargetResponseCard atau diamkan
 * saja - TIDAK menghambat alur approval atasan.
 */
class ShiftSwapTargetResponseRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ShiftSwapRequests $swapRequest) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SHIFT_SWAP_TARGET_RESPONSE_REQUESTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Diminta Tanggapan Tukar Shift';
    }

    protected function body(): string
    {
        $requesterName = $this->swapRequest->requester?->name ?? 'Rekan kerja';

        return "{$requesterName} mengajukan tukar shift dengan jadwal Anda. Tanggapan Anda opsional.";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'shift_swap_request_id' => $this->swapRequest->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['shift_swap_request_id' => $this->swapRequest->id]);
    }
}
