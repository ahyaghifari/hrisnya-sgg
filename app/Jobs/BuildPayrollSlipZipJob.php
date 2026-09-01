<?php

namespace App\Jobs;

use App\Models\Payroll\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Bangun ZIP slip gaji satu periode di background (lihat PayrollService::buildSlipZipToStorage).
 * Sinkron di request web bikin memory/timeout exceeded begitu jumlah karyawan banyak
 * (apalagi lintas rumah sakit). Progress ditandai lewat cache key `slipzip-building:{periodId}`
 * — di-set saat dispatch di PayrollsPage, dibersihkan di sini (TTL 1 jam jadi cadangan kalau
 * worker mati tanpa sempat lewat handle()/failed()).
 */
class BuildPayrollSlipZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public string $periodId,
        public ?string $requestedByUserId = null,
    ) {}

    public function handle(PayrollService $service): void
    {
        try {
            $period = PayrollPeriod::with('company')->find($this->periodId);

            if ($period) {
                $service->buildSlipZipToStorage($period);
            }
        } finally {
            Cache::forget("slipzip-building:{$this->periodId}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Cache::forget("slipzip-building:{$this->periodId}");

        Log::error('Build ZIP slip gaji gagal', [
            'period_id' => $this->periodId,
            'message' => $exception->getMessage(),
        ]);
    }
}
