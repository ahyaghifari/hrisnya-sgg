<?php

namespace App\Services\MeritPoint;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Enum\MeritPoint\MeritPointEvent;
use App\Enum\MeritPoint\MeritPointTriggerType;
use App\Enum\MeritPoint\MeritPointType;
use App\Models\MeritPoint\MeritPointRule;
use App\Models\MeritPoint\MeritPointTransaction;
use App\Models\Setting;
use App\Models\UserHrBalance;
use Illuminate\Support\Facades\DB;

/**
 * Merit Point masih berstatus DEMO (planning/2026-09-23-sp-dan-merit-point.md §3). Sinkronisasi
 * cache user_hr_balances.merit_point (§3.5), TIDAK menyentuh active_sp_level/active_sp_id
 * (dikelola terpisah oleh SpService).
 *
 * Cara hitung: saldo MULAI dari 100 (karyawan tanpa transaksi = skor penuh, sama dengan widget
 * tab Karir), lalu ledger diputar berurutan dan dijepit di LANTAI 0 di SETIAP transaksi - persis
 * contoh dokumen acuan §9 (100 +15 = 115, lalu -5 = 110). Bukan SUM lalu dijepit sekali di
 * akhir: itu bikin transaksi pertama dihitung dari 0 (-5 jadi 0, bukan 95) kalau urutannya
 * penalti duluan. Ledger kecil per karyawan (demo), jadi diputar ulang penuh tiap kali - bukan
 * disimpan inkremental.
 *
 * "Poin Bonus" (instruksi user 2026-09-27): saldo TIDAK lagi dijepit di 100 - boleh lewat
 * (112, 101, dst), widget tab Karir menampilkan kelebihannya sebagai badge "+12" terpisah dari
 * angka 100. Supaya tidak semua rule Reward otomatis "meledak" tak terbatas past 100, rule bisa
 * diisi kolom `bonus_points` (biasanya lebih kecil dari `points`) - begitu saldo SUDAH >= 100,
 * rule itu pakai `bonus_points`, bukan `points`, lihat effectivePoints(). Kolom kosong (null) =
 * rule tetap pakai `points` apa adanya walau sudah >= 100 (perilaku lama).
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

    /**
     * Terapkan rule AUTOMATIC aktif milik company untuk $event. Diam-diam skip kalau Merit Point
     * belum diaktifkan, rule tidak ada, atau kejadian yang sama ($sourceType+$sourceId) sudah
     * pernah dicatat (idempotent - aman dipanggil ulang tiap record disimpan).
     */
    public function applyEvent(MeritPointEvent $event, string $userId, ?string $companyId, string $sourceType, string $sourceId, string $reason): ?MeritPointTransaction
    {
        if (! $companyId || ! Setting::resolve(
            KepegawaianSettingEnum::MERIT_POINT_ENABLED->value,
            $companyId,
            filter_var(KepegawaianSettingEnum::MERIT_POINT_ENABLED->default(), FILTER_VALIDATE_BOOLEAN)
        )) {
            return null;
        }

        $rule = MeritPointRule::where('company_id', $companyId)
            ->where('trigger_type', MeritPointTriggerType::AUTOMATIC->value)
            ->where('event', $event->value)
            ->where('is_active', true)
            ->first();

        if (! $rule || MeritPointTransaction::where('rule_id', $rule->id)->where('source_type', $sourceType)->where('source_id', $sourceId)->exists()) {
            return null;
        }

        return $this->addTransaction([
            'user_id' => $userId,
            'rule_id' => $rule->id,
            'points' => $this->effectivePoints($rule, $userId),
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    /**
     * Poin yang BENERAN dipakai untuk 1 kejadian rule $rule pada karyawan $userId, dengan poin
     * bonus diperhitungkan - satu-satunya tempat logic ini ditulis, dipakai baik jalur otomatis
     * (applyEvent di atas) maupun manual "Pakai Aturan" (EmployeeStatistics::saveMeritTransaction).
     * Cuma rule Reward yang punya `bonus_points` terisi yang kena efek ini; Penalty/Contribution
     * atau Reward tanpa bonus_points tetap pakai `points` apa adanya, berapa pun saldo sekarang.
     */
    public function effectivePoints(MeritPointRule $rule, string $userId): int
    {
        if ($rule->type !== MeritPointType::REWARD || $rule->bonus_points === null) {
            return $rule->points;
        }

        return $this->computeBalance($userId) >= self::BASELINE ? $rule->bonus_points : $rule->points;
    }

    /** Batalkan transaksi otomatis suatu kejadian (mis. SP dibatalkan, keterlambatan dikoreksi jadi 0). */
    public function revokeEvent(string $userId, string $sourceType, string $sourceId): void
    {
        $deleted = MeritPointTransaction::where('user_id', $userId)
            ->where('source_type', $sourceType)->where('source_id', $sourceId)->delete();

        if ($deleted) {
            $this->refreshBalance($userId);
        }
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

    /** Lantai 0 saja - TIDAK ada lagi plafon 100 (instruksi user 2026-09-27, lihat docblock
     * class). Penalti tetap tidak bisa bikin saldo negatif. */
    private function clamp(int $value): int
    {
        return max(0, $value);
    }
}
