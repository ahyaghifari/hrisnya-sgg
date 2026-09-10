<?php

namespace App\Models\Sikartap;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rewrite ide_inovasi legacy — lihat migration create_sikartap_ide_inovasis_table untuk
 * penjelasan 3 tingkat (atasan wajib, direktur/komisaris opsional).
 */
class SikartapIdeInovasi extends Model
{
    use HasUlids;

    protected $table = 'sikartap_ide_inovasis';

    protected $guarded = ['id', 'sikartap_id', 'created_at', 'updated_at'];

    public function sikartap(): BelongsTo
    {
        return $this->belongsTo(Sikartap::class);
    }

    public function reviewerAtasan(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by_atasan');
    }

    public function reviewerDirektur(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by_direktur');
    }

    public function reviewerKomisaris(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by_komisaris');
    }

    /**
     * Catat keputusan 1 tingkat (direktur/komisaris): status + catatan + reviewer + waktu,
     * lalu kabari karyawan. Dipakai PengajuanDetailPage (per-record) & DirekturBulkIdeInovasiPage.
     *
     * @param  'direktur'|'komisaris'  $tier
     * @param  'approved'|'rejected'  $status
     */
    public function recordTierDecision(string $tier, string $status, string $actorId, ?string $note): void
    {
        $note = trim((string) $note) !== '' ? $note : null;

        $this->update([
            "status_{$tier}" => $status,
            "catatan_{$tier}" => $note,
            "reviewed_by_{$tier}" => $actorId,
            "reviewed_at_{$tier}" => now(),
        ]);

        $this->sikartap?->user?->notify(
            new \App\Notifications\Sikartap\SikartapIdeInovasiDireview($this->sikartap_id, $tier, $status, $note)
        );
    }

    /**
     * Rubrik skoring atasan (upload_ide_inovasi.php legacy): 5 kriteria 0-5, bobot berbeda,
     * dijumlah jadi skala 0-100. Lulus atasan kalau >= 75.
     */
    public const BOBOT = [
        'skor_identifikasi_masalah' => 20,
        'skor_analisis_masalah' => 25,
        'skor_kualitas_inovasi' => 30,
        'skor_potensi_manfaat' => 15,
        'skor_presentasi' => 10,
    ];

    public const AMBANG_LULUS = 75;

    /** @param array<string, int> $skor keyed sama self::BOBOT, tiap nilai 0-5 */
    public static function hitungNilaiAkhir(array $skor): float
    {
        $total = 0;

        foreach (self::BOBOT as $key => $bobot) {
            $total += ($skor[$key] ?? 0) / 5 * $bobot;
        }

        return round($total, 2);
    }

    public static function klasifikasiDariNilai(float $nilai): string
    {
        return match (true) {
            $nilai >= 90 => 'Sangat Baik',
            $nilai >= self::AMBANG_LULUS => 'Baik',
            $nilai >= 60 => 'Cukup',
            default => 'Kurang',
        };
    }
}
