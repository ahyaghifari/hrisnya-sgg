<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Payroll\Payroll;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Dikirim ke karyawan pemilik payroll SAAT ditandai sudah dibayar (PayrollDetailPage::markAsPaid()
 * / PayrollsPage::markSelectedAsPaid()) — bukan saat email slip gaji terkirim (itu proses
 * terpisah lewat App\Jobs\SendPayrollSlipJob, karyawan bisa saja belum sempat dikirimi email).
 */
class PayrollPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Payroll $payroll) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::PAYROLL_PAID)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Gaji Sudah Dibayar';
    }

    protected function body(): string
    {
        $period = $this->payroll->payrollPeriod->end_date->translatedFormat('F Y');

        return "Gaji Anda periode {$period} sudah ditandai dibayar.";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'payroll_id' => $this->payroll->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['payroll_id' => $this->payroll->id])
            ->action('Lihat', 'view');
    }
}
