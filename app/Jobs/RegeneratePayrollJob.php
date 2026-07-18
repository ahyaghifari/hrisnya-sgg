<?php

namespace App\Jobs;

use App\Models\Payroll\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegeneratePayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(
        public string $payrollPeriodId,
        public ?string $generatedByUserId = null,
    ) {}

    public function handle(PayrollService $service): void
    {
        $period = PayrollPeriod::findOrFail($this->payrollPeriodId);

        $service->regeneratePayroll($period, $this->generatedByUserId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payroll regeneration failed', [
            'payroll_period_id' => $this->payrollPeriodId,
            'message'           => $exception->getMessage(),
        ]);
    }
}
