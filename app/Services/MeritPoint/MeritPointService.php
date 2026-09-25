<?php

namespace App\Services\MeritPoint;

use App\Models\MeritPoint\MeritPointTransaction;
use App\Models\UserHrBalance;
use Illuminate\Support\Facades\DB;

/**
 * Merit Point masih berstatus DEMO (planning/2026-09-23-sp-dan-merit-point.md §3). Sinkronisasi
 * cache user_hr_balances.merit_point (§3.5), TIDAK menyentuh active_sp_level/active_sp_id
 * (dikelola terpisah oleh SpService).
 *
 * Cara hitung: saldo MULAI dari 100 (karyawan tanpa transaksi = skor penuh, sama dengan widget
 * tab Karir), lalu ledger diputar berurutan dan dijepit 0..100 di SETIAP transaksi - persis
 * contoh dokumen acuan §9 (100 +15 = 100, lalu -5 = 95). Bukan SUM lalu dijepit sekali di
 * akhir: itu bikin penalti "tertelan" reward yang tadinya kelebihan di atas 100, dan transaksi
 * pertama dihitung dari 0 (-5 jadi 0, bukan 95). Ledger kecil per karyawan (demo), jadi
 * diputar ulang penuh tiap kali - bukan disimpan inkremental.
 */
class MeritPointService
{
    public const BASELINE = 100;

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
        return UserHrBalance::updateOrCreate(
            ['user_id' => $userId],
            ['merit_point' => $this->computeBalance($userId)]
        );
    }

    /** Saldo saat ini dihitung langsung dari ledger (bukan dari cache user_hr_balances). */
    public function computeBalance(string $userId): int
    {
        return MeritPointTransaction::where('user_id', $userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('points')
            ->reduce(fn (int $balance, int $points) => $this->clamp($balance + $points), self::BASELINE);
    }

    /** Saldo seandainya $delta ditambahkan sekarang - buat pratinjau di form, tidak menyimpan apa pun. */
    public function previewBalance(string $userId, int $delta): int
    {
        return $this->clamp($this->computeBalance($userId) + $delta);
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
