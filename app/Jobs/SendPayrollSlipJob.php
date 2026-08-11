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

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $payrollId,
    ) {}

    /** Throttle burst rate ke SMTP Gmail (lihat AppServiceProvider::boot()) - job yang kena
     * limit otomatis di-release balik ke queue, bukan gagal/retry-count berkurang. */
    public function middleware(): array
    {
        return [new RateLimited('payroll-slip-email')];
    }

    public function handle(PayrollService $service): void
    {
        $payroll = Payroll::with([
            'user.profile',
            'payrollPeriod.signer.activeJobPosition.jobPosition',
            'payrollPeriod.generatedBy.activeJobPosition.jobPosition',
        ])->find($this->payrollId);

        if (! $payroll || ! $payroll->is_paid || ! $payroll->user?->email) {
            return;
        }

        // Sama seperti PDF slip: signer_id dipilih manual di Periode Gaji, fallback ke generatedBy kalau kosong.
        $sender = $payroll->payrollPeriod->signer ?? $payroll->payrollPeriod->generatedBy;
        $slip = $service->encryptedSlip($payroll);

        Mail::to($payroll->user->email)->send(new PayrollSlipMail(
            $payroll,
            $slip['binary'],
            $slip['filename'],
            $sender?->name ?? 'NOOR AFINI, SE',
            $sender?->activeJobPosition?->jobPosition?->name ?? 'Accounting',
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
