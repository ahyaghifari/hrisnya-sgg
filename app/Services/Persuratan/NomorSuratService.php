<?php

namespace App\Services\Persuratan;

use App\Enum\JenisSurat;
use App\Models\Persuratan\Perusahaan;
use App\Models\Persuratan\Surat;
use App\Models\Persuratan\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Format sama sistem lama: {urut:3}/{jenis}.{unit}/{perusahaan}/{bulan romawi}/{tahun}.
 * $isi: judul_surat (wajib), perihal, deskripsi (opsional). Surat keluar saja — surat masuk tidak lewat generator.
 * Perusahaan diambil dari unit. Counter per (perusahaan, tahun), TIDAK reset per bulan, dipakai bersama semua unit & jenis.
 */
class NomorSuratService
{
    private const ROMAWI = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public function generate(Unit $unit, JenisSurat $jenis, Carbon $tanggal, array $isi, ?object $user = null): Surat
    {
        return DB::connection('persuratan')->transaction(function () use ($jenis, $unit, $tanggal, $isi, $user) {
            $perusahaan = $unit->perusahaan;
            $key = ['perusahaan_id' => $perusahaan->id, 'tahun' => $tanggal->year];

            DB::connection('persuratan')->table('nomor_surat_counter')->insertOrIgnore($key + ['nomor_terakhir' => 0]);
            $row = DB::connection('persuratan')->table('nomor_surat_counter')->where($key)->lockForUpdate()->first();
            $urut = $row->nomor_terakhir + 1;
            DB::connection('persuratan')->table('nomor_surat_counter')->where($key)->update(['nomor_terakhir' => $urut]);

            return Surat::create([
                'nomor' => sprintf('%03d/%s.%s/%s/%s/%d', $urut, $jenis->value, $unit->kode, $perusahaan->kode_surat, self::ROMAWI[$tanggal->month - 1], $tanggal->year),
                'nomor_urut' => $urut,
                'jenis' => $jenis,
                'unit_id' => $unit->id,
                'tanggal' => $tanggal->toDateString(),
                'judul_surat' => $isi['judul_surat'],
                'perihal' => $isi['perihal'] ?? null,
                'deskripsi' => $isi['deskripsi'] ?? null,
                'dibuat_oleh_id' => $user?->getAuthIdentifier(),
                'dibuat_oleh_nama' => $user?->name,
            ]);
        });
    }
}
