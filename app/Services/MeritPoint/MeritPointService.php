<?php

namespace App\Services\MeritPoint;

use App\Models\MeritPoint\MeritPointTransaction;
use App\Models\UserHrBalance;
use Illuminate\Support\Facades\DB;

/**
 * Merit Point masih berstatus DEMO (planning/2026-09-23-sp-dan-merit-point.md §3). Sinkronisasi
 * cache user_hr_balances.merit_point (§3.5) - clamp 0-100, dihitung ulang dari SUM ledger tiap
 * ada transaksi baru, TIDAK menyentuh active_sp_level/active_sp_id (dikelola terpisah oleh
 * SpService).
 */
class MeritPointService
{
    public function addTransaction(array $data): MeritPointTransaction
    {
        return DB::transaction(function () use ($data) {
            $transaction = MeritPointTransaction::create($data);

            $this->refreshBalance($transaction->user_id);

            return $transaction;
        });
    }

    public function refreshBalance(string $userId): UserHrBalance
    {
        $total = (int) MeritPointTransaction::where('user_id', $userId)->sum('points');
        $clamped = max(0, min(100, $total));

        return UserHrBalance::updateOrCreate(
            ['user_id' => $userId],
            ['merit_point' => $clamped]
        );
    }
}
