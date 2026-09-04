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
    ) {
        // Berat (ratusan PDF dompdf, bisa menit-an) — jangan di tabel/queue `jobs` sampai
        // menahan notif absensi. Numpang koneksi + tabel slip (payroll_slip_jobs), worker terpisah.
        $this->onConnection(SendPayrollSlipJob::CONNECTION)->onQueue(SendPayrollSlipJob::QUEUE);
    }

    public function handle(PayrollService $service): void
    {
        // Worker terpisah — aman naikin limit. buildSlipZipToStorage() sudah tulis PDF ke
        // disk (bukan tahan di memory), ini cuma jaring pengaman buat 1 render dompdf berat.
        @ini_set('memory_limit', '512M');

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
