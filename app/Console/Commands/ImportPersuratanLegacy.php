<?php

namespace App\Console\Commands;

use App\Enum\JenisSurat;
use App\Models\Persuratan\Perusahaan;
use App\Models\Persuratan\Surat;
use App\Models\Persuratan\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impor data surat lama dari tabel `legacy_*` (di-dump apa adanya ke DB `persuratan`) ke tabel bersih.
 * Satu transaksi; --dry-run menjalankan semuanya lalu ROLLBACK (cuma laporan). Idempoten lewat legacy_id.
 *
 * Tabel/kolom legacy yang dibaca:
 *  legacy_jenis_surat(id, kode_surat) · legacy_departemen(id, id_perusahaan) · legacy_unit(id, nama_unit, kode_unit, id_departemen)
 *  legacy_surat(id, nomor_surat, tipe_surat, status, judul_surat, perihal, deskripsi, id_perusahaan,
 *               id_unit, id_jenis_surat, id_user_pembuat, tanggal_surat, created_at)
 *  legacy_nomor_surat_counter(id_perusahaan, tahun, nomor_terakhir)   [opsional]
 *  legacy_kode_surat_unit(id_unit, kode)                              [opsional; didahulukan atas unit.kode_unit]
 *
 * perusahaan.id sengaja SAMA dengan id sistem lama (lihat PersuratanPerusahaanSeeder). Perusahaan tiap
 * unit lama diturunkan lewat unit -> departemen -> perusahaan, SEMUA unit dibuat (juga yang belum pernah
 * dipakai di surat). Kalau surat memakai unit yang perusahaannya beda dari perusahaan surat itu, dibuatkan
 * baris unit tambahan di perusahaan surat (dilaporkan). Surat tanpa unit / unit tak dikenal masuk ke
 * unit placeholder "(Tanpa unit)" per perusahaan — tidak ada surat yang dibuang diam-diam, kecuali
 * perusahaan/jenis/nomor bermasalah (dilaporkan).
 */
class ImportPersuratanLegacy extends Command
{
    protected $signature = 'persuratan:import-legacy {--dry-run : Jalankan semua lalu rollback, hanya laporan}';

    protected $description = 'Impor surat lama (tabel legacy_*) ke tabel persuratan bersih';

    private const CONN = 'persuratan';

    /** @var array<string, list<string>> */
    private array $masalah = [];

    public function handle(): int
    {
        $db = DB::connection(self::CONN);

        $wajib = ['legacy_jenis_surat', 'legacy_departemen', 'legacy_unit', 'legacy_surat'];
        $hilang = array_filter($wajib, fn ($t) => ! Schema::connection(self::CONN)->hasTable($t));
        if ($hilang) {
            $this->error('Tabel legacy belum ada di DB persuratan: '.implode(', ', $hilang));

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $db->beginTransaction();

        try {
            $laporan = $this->impor($db);
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $dry ? $db->rollBack() : $db->commit();

        $this->table(['Item', 'Jumlah'], collect($laporan)->map(fn ($v, $k) => [$k, $v])->values()->all());
        foreach ($this->masalah as $judul => $baris) {
            $this->warn("{$judul} (".count($baris).')');
            foreach (array_slice($baris, 0, 15) as $b) {
                $this->line("  - {$b}");
            }
            if (count($baris) > 15) {
                $this->line('  … dan '.(count($baris) - 15).' lainnya');
            }
        }
        $this->info($dry ? 'DRY-RUN selesai — tidak ada yang ditulis.' : 'Impor selesai.');

        return self::SUCCESS;
    }

    private function catat(string $judul, string $baris): void
    {
        $this->masalah[$judul][] = $baris;
    }

    /** @return array<string, int|string> */
    private function impor($db): array
    {
        $perusahaan = Perusahaan::pluck('id', 'id');   // id baru = id lama (lihat PersuratanPerusahaanSeeder)
        $jenis = $db->table('legacy_jenis_surat')->pluck('kode_surat', 'id');
        $legacyUnit = $db->table('legacy_unit')->get()->keyBy('id');

        $unitCache = [];   // "perusahaanId:legacyUnitId" => unit id
        $unitMilik = $this->imporUnit($db, $perusahaan, $legacyUnit, $unitCache);   // legacyUnitId => perusahaanId
        $nomorAda = Surat::pluck('id', 'nomor')->all();
        $maxUrut = [];     // "perusahaanId:tahun" => nomor urut tertinggi (surat keluar)
        $n = ['surat dibaca' => 0, 'surat baru' => 0, 'surat sudah ada (dilewati)' => 0, 'surat dilewati (bermasalah)' => 0];

        foreach ($db->table('legacy_surat')->orderBy('id')->cursor() as $s) {
            $n['surat dibaca']++;

            $pid = $perusahaan[$s->id_perusahaan] ?? null;
            if (! $pid) {
                $this->catat('Perusahaan lama belum ada di tabel perusahaan (tambah dengan id yang sama)', "surat {$s->id}: id_perusahaan={$s->id_perusahaan}");
                $n['surat dilewati (bermasalah)']++;

                continue;
            }

            $kodeJenis = $jenis[$s->id_jenis_surat] ?? null;
            if (! $kodeJenis || ! JenisSurat::tryFrom($kodeJenis)) {
                $this->catat('Jenis surat tidak dikenal', "surat {$s->id}: id_jenis_surat={$s->id_jenis_surat} kode=".($kodeJenis ?? 'null'));
                $n['surat dilewati (bermasalah)']++;

                continue;
            }

            if (Surat::where('legacy_id', $s->id)->exists()) {
                $n['surat sudah ada (dilewati)']++;

                continue;
            }

            if (isset($nomorAda[$s->nomor_surat])) {
                $this->catat('Nomor kembar (tidak diimpor)', "surat {$s->id}: {$s->nomor_surat}");
                $n['surat dilewati (bermasalah)']++;

                continue;
            }

            $unitId = $this->unitId($perusahaan[$s->id_perusahaan], $s->id_unit, $legacyUnit, $unitMilik, $unitCache);

            $surat = Surat::create([
                'legacy_id' => $s->id,
                'legacy_user_id' => $s->id_user_pembuat,
                'nomor' => $s->nomor_surat,
                'tipe_surat' => $s->tipe_surat,
                'status' => $s->status,
                'nomor_urut' => null,
                'jenis' => $kodeJenis,
                'unit_id' => $unitId,
                'tanggal' => $s->tanggal_surat,
                'judul_surat' => $s->judul_surat,
                'perihal' => $s->perihal,
                'deskripsi' => $s->deskripsi,
            ]);
            $surat->forceFill(['created_at' => $s->created_at])->saveQuietly();

            $nomorAda[$s->nomor_surat] = $surat->id;
            $n['surat baru']++;

            // Nomor urut = 3 digit pertama nomor surat keluar format "001/...".
            if ($s->tipe_surat === 'keluar' && preg_match('#^(\d+)/#', $s->nomor_surat, $m)) {
                $surat->forceFill(['nomor_urut' => (int) $m[1]])->saveQuietly();
                $tahun = date('Y', strtotime($s->tanggal_surat));
                $k = "{$pid}:{$tahun}";
                $maxUrut[$k] = max($maxUrut[$k] ?? 0, (int) $m[1]);
            }
        }

        $n += $this->counter($db, $perusahaan, $maxUrut);
        $n['unit dibuat/ada'] = count($unitCache);

        return $n;
    }

    /**
     * Buat semua unit lama di perusahaan departemennya. Unit tanpa kode_unit tetap dibawa (kode kosong) —
     * tidak bisa dipakai membuat nomor sampai dilengkapi di /surat/units.
     *
     * @return array<int, int> legacyUnitId => perusahaan.id
     */
    private function imporUnit($db, $perusahaan, $legacyUnit, array &$cache): array
    {
        $perusahaanDept = $db->table('legacy_departemen')->pluck('id_perusahaan', 'id');
        // Kode unit sistem lama terpisah di kode_surat_unit(id_unit, kode); unit.kode_unit sering kosong.
        $kodeTerpisah = Schema::connection(self::CONN)->hasTable('legacy_kode_surat_unit')
            ? $db->table('legacy_kode_surat_unit')->orderBy('id')->pluck('kode', 'id_unit')
            : collect();
        $milik = [];

        foreach ($legacyUnit as $lu) {
            $kode = (string) ($kodeTerpisah[$lu->id] ?? $lu->kode_unit ?? '');
            if (filled($kodeTerpisah[$lu->id] ?? null) && filled($lu->kode_unit) && $kodeTerpisah[$lu->id] !== $lu->kode_unit) {
                $this->catat('Kode unit beda: kode_surat_unit vs unit.kode_unit (dipakai kode_surat_unit)', "unit {$lu->id} {$lu->nama_unit}: '{$kodeTerpisah[$lu->id]}' vs '{$lu->kode_unit}'");
            }

            $pid = $perusahaan[$perusahaanDept[$lu->id_departemen] ?? 0] ?? null;
            if (! $pid) {
                $this->catat('Unit lama tanpa perusahaan terpetakan (tidak dibuat sendiri)', "unit {$lu->id} {$lu->nama_unit}: id_departemen={$lu->id_departemen}");

                continue;
            }

            if (blank($kode)) {
                $this->catat('Unit tanpa kode_unit (lengkapi di /surat/units)', "unit {$lu->id} {$lu->nama_unit}");
            }

            $milik[$lu->id] = $pid;
            $cache["{$pid}:{$lu->id}"] = Unit::firstOrCreate(
                ['perusahaan_id' => $pid, 'legacy_id' => $lu->id],
                ['kode' => $kode, 'nama' => $lu->nama_unit],
            )->id;
        }

        return $milik;
    }

    private function unitId(int $pid, ?int $legacyUnitId, $legacyUnit, array $unitMilik, array &$cache): int
    {
        $lu = $legacyUnitId ? ($legacyUnit[$legacyUnitId] ?? null) : null;
        if ($legacyUnitId && ! $lu) {
            $this->catat('Unit lama tidak ditemukan di legacy_unit (dipindah ke "(Tanpa unit)")', "id_unit={$legacyUnitId}");
        }

        if ($lu && isset($unitMilik[$lu->id]) && $unitMilik[$lu->id] !== $pid) {
            $this->catat('Unit dipakai surat perusahaan lain dari perusahaan departemennya (dibuat baris unit tambahan)', "unit lama {$lu->id} ({$lu->nama_unit}): departemen→perusahaan {$unitMilik[$lu->id]}, surat→perusahaan {$pid}");
        }

        $key = $pid.':'.($lu ? $lu->id : 0);

        return $cache[$key] ??= $lu
            ? Unit::firstOrCreate(
                ['perusahaan_id' => $pid, 'legacy_id' => $lu->id],
                ['kode' => (string) (Unit::where('legacy_id', $lu->id)->value('kode') ?: $lu->kode_unit), 'nama' => $lu->nama_unit],
            )->id
            : Unit::firstOrCreate(
                ['perusahaan_id' => $pid, 'legacy_id' => null, 'nama' => '(Tanpa unit)'],
                ['kode' => '-'],
            )->id;
    }

    /** Counter baru = max(counter lama, nomor urut tertinggi di surat) supaya nomor berikutnya tidak bentrok. */
    private function counter($db, $perusahaan, array $maxUrut): array
    {
        $lama = [];
        if (Schema::connection(self::CONN)->hasTable('legacy_nomor_surat_counter')) {
            foreach ($db->table('legacy_nomor_surat_counter')->get() as $c) {
                if ($pid = $perusahaan[$c->id_perusahaan] ?? null) {
                    $lama["{$pid}:{$c->tahun}"] = (int) $c->nomor_terakhir;
                }
            }
        } else {
            $this->catat('legacy_nomor_surat_counter tidak ada', 'counter dihitung dari nomor tertinggi di surat saja');
        }

        $n = 0;
        foreach ($lama + $maxUrut as $key => $_) {
            [$pid, $tahun] = explode(':', $key);
            $a = $lama[$key] ?? 0;
            $b = $maxUrut[$key] ?? 0;
            if ($a !== $b) {
                $this->catat('Counter lama ≠ nomor tertinggi di surat (dipakai yang terbesar)', "perusahaan {$pid} tahun {$tahun}: counter={$a}, surat={$b}");
            }
            $db->table('nomor_surat_counter')->updateOrInsert(
                ['perusahaan_id' => $pid, 'tahun' => $tahun],
                ['nomor_terakhir' => max($a, $b)],
            );
            $n++;
        }

        return ['counter diisi' => $n];
    }
}
