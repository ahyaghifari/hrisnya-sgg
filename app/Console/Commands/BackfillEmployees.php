<?php

namespace App\Console\Commands;

use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeCareer;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Services\Identity\EmployeeSyncService;
use Illuminate\Console\Command;

/**
 * Salin users + user_profiles + user_job_positions ke employees & employee_careers (lihat
 * EmployeeSyncService). Idempotent - aman diulang. --dry-run tidak menulis apa pun. Jalankan
 * SETELAH identity:backfill (persons); kalau person belum ada, service membuatnya sendiri.
 * 2x akun Super Admin tidak dapat baris employee (bukan karyawan).
 */
class BackfillEmployees extends Command
{
    protected $signature = 'identity:backfill-employees {--dry-run : Hitung saja, tanpa menulis} {--user= : Batasi ke satu NIP}';

    protected $description = 'Salin ke employees + employee_careers (idempotent)';

    public function handle(EmployeeSyncService $sync): int
    {
        $dry = (bool) $this->option('dry-run');

        $users = User::withoutGlobalScopes()->withTrashed()
            ->whereNotIn('id', EmployeeSyncService::skippedUserIds())
            ->when($this->option('user'), fn ($q, $nip) => $q->where('nip', $nip));

        $total = (clone $users)->count();
        $existing = Employee::whereIn('user_id', (clone $users)->select('id'))->count();
        $ledger = UserJobPositions::whereIn('user_id', (clone $users)->select('id'))->count();

        $this->info(($dry ? '[DRY-RUN] ' : '') . "karyawan: {$total}, sudah punya employee: {$existing}, akan dibuat: " . ($total - $existing));
        $this->line("baris riwayat jabatan (user_job_positions): {$ledger}, sudah tersalin: " . EmployeeCareer::count());

        if ($dry) {
            return self::SUCCESS;
        }

        $failed = 0;

        $users->chunkById(200, function ($chunk) use ($sync, &$failed) {
            foreach ($chunk as $user) {
                try {
                    $sync->syncUser($user);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("NIP {$user->nip}: {$e->getMessage()}");
                }
            }
        });

        $this->info('Selesai. Gagal: ' . $failed);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
