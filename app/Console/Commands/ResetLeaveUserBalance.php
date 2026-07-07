<?php

namespace App\Console\Commands;

use App\Models\Attendance\LeaveUserBalances;
use App\Models\Master\Attendance\LeaveTypes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetLeaveUserBalance extends Command
{
    protected $signature = 'leave:reset-balance {period : The reset period to process (yearly or monthly)}';

    protected $description = 'Reset leave user balances based on reset period (yearly or monthly)';

    public function handle(): int
    {
        $period = $this->argument('period');

        if (!in_array($period, ['yearly', 'monthly'])) {
            $this->error("Invalid period '{$period}'. Use 'yearly' or 'monthly'.");
            return self::FAILURE;
        }

        $now = now();

        if ($period === 'yearly') {
            $periodStart = $now->copy()->startOfYear();
            $periodEnd   = $now->copy()->endOfYear();
        } else {
            $periodStart = $now->copy()->startOfMonth();
            $periodEnd   = $now->copy()->endOfMonth();
        }

        $leaveTypes = LeaveTypes::where('reset_period', $period)->get();

        if ($leaveTypes->isEmpty()) {
            $this->info("No leave types found with reset_period = '{$period}'. Nothing to reset.");
            return self::SUCCESS;
        }

        $this->info("Found {$leaveTypes->count()} leave type(s) with reset_period = '{$period}'.");
        $this->info("New period: {$periodStart->toDateString()} → {$periodEnd->toDateString()}");

        $resetCount = 0;
        $errorCount = 0;

        foreach ($leaveTypes as $leaveType) {
            $userIds = LeaveUserBalances::where('leave_type_id', $leaveType->id)
                ->distinct()
                ->pluck('user_id');

            if ($userIds->isEmpty()) {
                $this->line("  [skip] Leave type '{$leaveType->name}' has no existing user balances.");
                continue;
            }

            $this->line("  Processing leave type '{$leaveType->name}' for {$userIds->count()} user(s)...");

            foreach ($userIds as $userId) {
                DB::beginTransaction();
                try {
                    LeaveUserBalances::updateOrCreate(
                        [
                            'user_id'       => $userId,
                            'leave_type_id' => $leaveType->id,
                            'period_start'  => $periodStart->toDateString(),
                        ],
                        [
                            'period_end' => $periodEnd->toDateString(),
                            'quota'      => $leaveType->quota_days,
                            'used'       => 0,
                        ]
                    );

                    DB::commit();
                    $resetCount++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $errorCount++;
                    Log::error("ResetLeaveUserBalance: failed for user_id={$userId}, leave_type_id={$leaveType->id}. Error: {$e->getMessage()}");
                    $this->error("    Failed for user {$userId}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Done. Reset: {$resetCount}, Failed: {$errorCount}.");

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
