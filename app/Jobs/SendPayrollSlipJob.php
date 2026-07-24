<?php

namespace App\Jobs;

use App\Mail\PayrollSlipMail;
use App\Models\Payroll\Payroll;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Nama & jabatan pengirim di email diambil dari user yang MENGKLIK tombol kirim (bukan
 * generatedBy payroll) — dikirim sebagai ID biar job tetap ringan di-serialize ke antrean.
 */
class SendPayrollSlipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $payrollId,
        public string $senderUserId,
    ) {}

    public function handle(PayrollService $service): void
    {
        $payroll = Payroll::with(['user.profile', 'payrollPeriod'])->find($this->payrollId);

        if (! $payroll || ! $payroll->is_paid || ! $payroll->user?->email) {
            return;
        }

        $sender = User::with('activeJobPosition.jobPosition')->find($this->senderUserId);
        $slip = $service->encryptedSlip($payroll);

        Mail::to($payroll->user->email)->send(new PayrollSlipMail(
            $payroll,
            $slip['binary'],
            $slip['filename'],
            $sender?->name ?? '-',
            $sender?->activeJobPosition?->jobPosition?->name,
        ));

        $payroll->update(['slip_sent_at' => now()]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Pengiriman slip gaji ke email gagal', [
            'payroll_id' => $this->payrollId,
            'message' => $exception->getMessage(),
        ]);
    }
}
