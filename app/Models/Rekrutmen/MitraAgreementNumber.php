<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Nomor perjanjian kerja per lamaran. Dua jenis, masing-masing urutannya sendiri (reset 001 saat
 * tahun berganti): `mitra` (RS) R-001/MITRA-HRD/RSUSM/{segmen}/{tahun} dan `pkwtt` (holding)
 * 001 / PKWTT.3A / SGG / {segmen} / {tahun}. Segmen tengah diisi manual. Tabel di DB HRIS (bukan
 * `recruitment`), dikunci per lamaran supaya cetak ulang tidak mengganti nomor.
 */
class MitraAgreementNumber extends Model
{
    public const KIND_MITRA = 'mitra';
    public const KIND_PKWTT = 'pkwtt';

    protected $guarded = ['id'];

    private static function format(string $kind, ?int $seq, string $segment, int $year): string
    {
        $no = $seq === null ? '000' : sprintf('%03d', $seq);

        return $kind === self::KIND_MITRA
            ? "R-{$no}/MITRA-HRD/RSUSM/{$segment}/{$year}"
            : "{$no} / PKWTT.3A / SGG / {$segment} / {$year}";
    }

    public static function nextSeq(string $kind, ?int $year = null): int
    {
        return (int) static::where('kind', $kind)->where('year', $year ?? now()->year)->max('seq') + 1;
    }

    /** Placeholder sebelum nomor dibuat — sama dengan format draft. */
    public static function placeholder(string $kind): string
    {
        return static::format($kind, null, '…', now()->year);
    }

    /** Usulan nomor lengkap berikutnya (segmen tengah masih "…"), untuk diedit manual oleh pengguna. */
    public static function suggest(string $kind): string
    {
        return static::format($kind, static::nextSeq($kind), '…', now()->year);
    }

    /** $number diisi = nomor lengkap manual (urut tetap tercatat untuk counter); kosong = dirakit dari $segment. */
    public static function issue(string $jobApplicationId, string $kind, string $segment, ?string $number = null): self
    {
        return DB::transaction(function () use ($jobApplicationId, $kind, $segment, $number) {
            $year = now()->year;
            // ponytail: unique(kind, year, seq) jaga dari dobel kalau dua recruiter klik bersamaan (error, ulangi klik).
            $seq = static::nextSeq($kind, $year);

            return static::create([
                'job_application_id' => $jobApplicationId,
                'kind' => $kind,
                'year' => $year,
                'seq' => $seq,
                'number' => $number ?: static::format($kind, $seq, $segment, $year),
            ]);
        });
    }
}
