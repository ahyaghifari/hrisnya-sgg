<?php

namespace App\Jobs;

use App\Mail\PayrollSlipMail;
use App\Models\Payroll\Payroll;
use App\Services\Payroll\PayrollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendPayrollSlipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Koneksi + queue khusus slip: TABEL TERPISAH `payroll_slip_jobs` (config/queue.php),
     * worker sendiri (`queue:work payroll-slip`). Batch kirim slip yang besar + di-rate-limit
     * + build ZIP tidak menumpuk di tabel `jobs` bersama notif absensi dll. Lihat
     * deployment/CHECKLIST.md §Queue. */
    public const CONNECTION = 'payroll-slip';
    public const QUEUE = 'payroll-slip';

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $payrollId,
    ) {
        $this->onConnection(self::CONNECTION)->onQueue(self::QUEUE);
    }

    /** Throttle burst rate ke SMTP Gmail (lihat AppServiceProvider::boot()) - job yang kena
     * limit otomatis di-release balik ke queue, bukan gagal/retry-count berkurang. */
    public function middleware(): array
    {
        return [new RateLimited('payroll-slip-email')];
    }

    public function handle(PayrollService $service, \App\Services\Payroll\PayrollMailer $mailer): void
    {
        $payroll = Payroll::with([
            'employee.user',
            'payrollPeriod.signer.activeJobPosition.jobPosition',
            'payrollPeriod.generatedBy.activeJobPosition.jobPosition',
        ])->find($this->payrollId);

        if (! $payroll || ! $payroll->is_paid) {
            return;
        }

        // Email pribadi (user_profiles.email_pribadi) diprioritaskan buat kirim slip - email akun
        // (users.email) kadang cuma format standar perusahaan buat login, belum tentu benar-benar
        // dicek karyawan buat terima dokumen pribadi. Fallback ke email akun kalau kosong.
        $recipientEmail = $payroll->employee?->email_pribadi ?: $payroll->employee?->user?->email;

        if (! $recipientEmail) {
            return;
        }

        // Sama seperti PDF slip: signer_id dipilih manual di Periode Gaji, fallback ke generatedBy kalau kosong.
        $sender = $payroll->payrollPeriod->signer ?? $payroll->payrollPeriod->generatedBy;
        $slip = $service->encryptedSlip($payroll);

        // Kirim lewat akun SMTP perusahaan ini kalau ada (payroll_mail_settings) — kalau tidak,
        // jatuh ke mailer default. From email juga ikut akun perusahaan.
        $mailSetting = $mailer->settingFor($payroll->payrollPeriod->company_id);

        $mailer->send($mailSetting, $recipientEmail, new PayrollSlipMail(
            $payroll,
            $slip['binary'],
            $slip['filename'],
            $sender?->name ?? 'NOOR AFINI, SE',
            $sender?->activeJobPosition?->jobPosition?->name ?? 'Accounting',
            $mailSetting?->from_address,
            $mailSetting?->from_name,
        ));

        $payroll->update(['slip_sent_at' => now(), 'slip_send_failed_at' => null, 'slip_send_error' => null]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Pengiriman slip gaji ke email gagal', [
            'payroll_id' => $this->payrollId,
            'message' => $exception->getMessage(),
        ]);

        Payroll::whereKey($this->payrollId)->update([
            'slip_send_failed_at' => now(),
            'slip_send_error' => Str::limit($exception->getMessage(), 250),
        ]);
    }
}
