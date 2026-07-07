<?php

namespace App\Jobs;

use App\Models\Payroll\DoctorPayrollPeriod;
use App\Services\Payroll\DoctorPayrollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDoctorPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(
        public string $payrollPeriodId
    ) {}

    public function handle(DoctorPayrollService $service): void
    {
        $period = DoctorPayrollPeriod::findOrFail($this->payrollPeriodId);

        if ($period->processed_at) {
            return;
        }

        $service->generatePayroll($period);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payroll generation failed', [
            'payroll_period_id' => $this->payrollPeriodId,
            'message' => $exception->getMessage(),
        ]);
    }
}
