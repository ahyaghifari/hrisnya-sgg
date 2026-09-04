<?php

namespace App\Services\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

/**
 * Transkripsi angka payroll final dari Excel skema RSU ke tabel Payroll/PayrollItem existing —
 * BUKAN menjalankan calculator. Lihat knowledge/markdown/import-payroll-excel-manual.md.
 *
 * - Dokter (STATUS KARYAWAN = Mitra) di-skip total.
 * - NIP tak ketemu User -> dilaporkan di preview, tidak diimport (baris lain tetap jalan).
 * - Identitas slip (jabatan/status/kelas/tgl masuk) di-snapshot ke payrolls.import_meta.
 * - Label baris slip = PayrollComponentEnum::label() (bukan label Excel).
 * - Nominal Rp0 -> baris PayrollItem tidak dibuat.
 */
class ManualPayrollExcelImporter
{
    /**
     * Kolom Excel dicari lewat TEKS HEADER (baris 1), bukan huruf kolom tetap — layout tiap RS
     * beda (mis. Barabai tak punya "Tunj. Dokter/Jabatan Khusus" & "Tunj. Jabatan/Beban Kerja",
     * jadi semua kolom setelahnya geser −2). `resolveColumns()` bikin peta huruf-kolom saat runtime
     * dari header, dicek `str_contains` versi lowercase + spasi dirapatkan.
     *
     * Konsep 1 kolom: needle string. Nilai `null` di RESOLVE_OPTIONAL = boleh tak ada.
     */
    private const HEADER_SIGNATURES = [
        // pendapatan
        'gaji_pokok'            => 'gaji pokok',
        'tunjangan_jabatan'     => 'tunj. jabatan skema baru', // HANYA kolom ini, bukan + "beban kerja"
        'insentif'              => 'insentif',
        'tunjangan_khusus'      => 'dokter/jabatan khusus',    // opsional
        'tunjangan_masa_kerja'  => 'tunjangan masa kerja',
        'jasa_pelayanan'        => 'jasa pelayanan baru',
        'jasa_unit_tambahan'    => 'tambahan jasa',
        'jasa_unit_ambulans'    => 'jasa supir ambulance',
        'jasa_unit_infus'       => 'jasa paket infus',
        'lembur'                => 'lembur (dihitung',
        'extra_shift'           => 'extra shift',
        'gross'                 => 'jumlah terima skema baru',
        // potongan (1 kolom)
        'bpjs_kesehatan'        => 'potongan bpjs 1%',
        'bpjs_ketenagakerjaan'  => 'potongan bpjstk',
        'izin_terlambat_pulang_cepat' => 'potongan izin terlambat',
        'izin_cuti_count'       => 'jmlh (cuti',               // -> quantity baris izin_cuti_gabung
        'izin_cuti_gabung'      => 'potongan izin dan cuti',
        'koperasi'              => 'potongan koperasi',
        'bon'                   => 'potongan bon',
        'deduction'             => 'total potongan',
        'net'                   => 'total gaji yang diterima skema baru',
        'potongan_khusus'       => 'hari efektif kontrak',     // opsional
    ];

    /**
     * Konsep multi-kolom: SEMUA kolom yg header-nya cocok dijumlah. "telat biasa" + "telat
     * istirahat/tidak absen" dua-duanya masuk 1 baris keterlambatan (persis perilaku lama AJ+AL).
     */
    private const HEADER_SIGNATURES_MULTI = [
        'telat_count'  => 'jumlah telat',    // -> quantity baris keterlambatan
        'keterlambatan' => 'potongan telat', // -> nominal baris keterlambatan
    ];

    /** Konsep yg boleh tak ketemu (layout RS tertentu memang tak punya kolomnya). */
    private const RESOLVE_OPTIONAL = ['tunjangan_khusus', 'potongan_khusus'];

    /** Label 3 jenis Jasa Unit -> tampil di slip "Jasa Unit" + rincian. */
    private const JASA_UNIT_LABELS = [
        'jasa_unit_tambahan' => 'Tambahan Jasa',
        'jasa_unit_ambulans' => 'Ambulans & Rujukan Pasien',
        'jasa_unit_infus'    => 'Paket Infus',
    ];

    /** source_type kustom (bukan PayrollComponentEnum). */
    public const SOURCE_POTONGAN_KHUSUS = 'potongan_khusus';
    public const SOURCE_TUNJANGAN_KHUSUS = 'tunjangan_khusus';

    /**
     * Label baris "Tunjangan Khusus" (kolom V) — beda per perusahaan, hardcode by companies.code.
     * Tambah entri di sini kalau RS lain punya sebutan sendiri. Fallback = 'Tunjangan Khusus'.
     */
    private const TUNJANGAN_KHUSUS_LABEL = [
        '0001' => 'Tunjangan Dokter/Jabatan Khusus',  // RSU Syifa Medika Banjarbaru
        '0002' => 'Tunjangan Dokter/Jabatan Khusus',  // RSU Syifa Medika Barabai (sesuaikan bila beda)
    ];
    private const TUNJANGAN_KHUSUS_LABEL_DEFAULT = 'Tunjangan Khusus';

    private const FIRST_DATA_ROW = 3;
    private const MAX_ROW = 600;

    /**
     * Baca file tanpa menulis apa pun. Cocokkan NIP -> User (company itu), susun komponen,
     * kumpulkan warning.
     *
     * @return array{
     *   summary: array, matched: list<array>, unmatched: list<array>, warnings: list<array>
     * }
     */
    public function preview(string $path, string $companyId): array
    {
        // Hitung rumus 480+ baris bisa 10-40 dtk (lihat parse()).
        @set_time_limit(300);

        $parsed = $this->parse($path, $this->tunjanganKhususLabel($companyId));

        $matched = [];
        $unmatched = [];
        $warnings = [];
        $totalNet = 0;

        foreach ($parsed['rows'] as $row) {
            $user = $this->resolveUser($row['nip']);

            if (! $user) {
                $unmatched[] = ['nip' => $row['nip'], 'nip_excel' => $row['nip_excel'], 'nama' => $row['nama'], 'jabatan' => $row['jabatan']];
                $warnings[] = ['type' => 'nip_tak_ketemu', 'nip' => $row['nip'], 'nama' => $row['nama'], 'detail' => "NIP Excel '{$row['nip_excel']}' tidak ada di data karyawan."];
                continue;
            }

            if ($user->company_id !== $companyId) {
                $warnings[] = ['type' => 'beda_company', 'nip' => $row['nip'], 'nama' => $row['nama'], 'detail' => "Karyawan '{$user->name}' ada di perusahaan lain — dilewati."];
                $unmatched[] = ['nip' => $row['nip'], 'nip_excel' => $row['nip_excel'], 'nama' => $row['nama'], 'jabatan' => $row['jabatan']];
                continue;
            }

            $totalNet += $row['net'];

            // Warning yang tidak memblokir import, cuma pemberitahuan sebelum kirim slip.
            if (! $user->profile?->email_pribadi && ! $user->email) {
                $warnings[] = ['type' => 'tanpa_email', 'nip' => $row['nip'], 'nama' => $user->name, 'detail' => 'Tidak punya email pribadi maupun email akun — slip tidak akan terkirim.'];
            }
            if (! $user->profile?->date_of_birth) {
                $warnings[] = ['type' => 'tanpa_dob', 'nip' => $row['nip'], 'nama' => $user->name, 'detail' => 'Tanggal lahir kosong — password PDF slip tidak bisa dibuat, email di-skip.'];
            }

            // Per-komponen (dijumlah per source_type) — buat tabel pratinjau lebar biar mudah
            // dicek kolom-per-kolom lawan file Excel.
            $amounts = [];
            foreach ([...$row['earnings'], ...$row['deductions']] as $line) {
                $amounts[$line['source_type']] = ($amounts[$line['source_type']] ?? 0) + $line['amount'];
            }

            $matched[] = [
                'nip' => $user->nip,
                'nip_excel' => $row['nip_excel'],
                'user_id' => $user->id,
                'nama' => $user->name,
                'nama_excel' => $row['nama'],
                'unit' => $row['unit'],
                'jabatan' => $row['jabatan'],
                'amounts' => $amounts,
                'gross' => $row['gross'],
                'deduction' => $row['deduction'],
                'net' => $row['net'],
                'net_excel' => $row['net_excel'],
                'net_cocok' => true,
                'item_count' => count($row['earnings']) + count($row['deductions']),
            ];
        }

        return [
            'summary' => [
                'baris_data' => count($parsed['rows']),
                'mitra_dilewati' => $parsed['mitra_skipped'],
                'cocok' => count($matched),
                'tak_cocok' => count($unmatched),
                'total_net' => $totalNet,
                'periode_serial' => $parsed['period_serial'],
                'payment_date' => $parsed['payment_date'],
            ],
            'matched' => $matched,
            'unmatched' => $unmatched,
            'warnings' => $warnings,
        ];
    }

    /**
     * Tulis Payroll + PayrollItem untuk baris yang cocok. Idempoten untuk yang BELUM dibayar:
     * baris Payroll is_paid=false dihapus & dibuat ulang. Payroll yang SUDAH dibayar dikunci —
     * tidak dihapus, dan baris Excel untuk karyawan itu dilewati (tidak menimpa).
     *
     * @return array{dibuat: int, dilewati: int, dilewati_dibayar: int, warnings: list<array>}
     */
    public function import(string $path, PayrollPeriod $period, User $actor): array
    {
        $preview = $this->preview($path, $period->company_id);

        // user_id yang payroll-nya di period ini sudah DIBAYAR — dikunci total, tidak ikut
        // di-replace import (baris Excel-nya dilewati di loop bawah).
        $paidUserIds = Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->where('is_paid', true)
            ->pluck('user_id');

        $parsedByNip = collect($this->parse($path, $this->tunjanganKhususLabel($period->company_id))['rows'])->keyBy('nip_excel');

        return DB::transaction(function () use ($preview, $parsedByNip, $period, $actor, $paidUserIds) {
            // Bersihkan payroll lama yang BELUM dibayar (+ item-nya) untuk period ini. Yang sudah
            // dibayar dibiarkan apa adanya.
            $oldIds = Payroll::where('payroll_period_id', $period->id)
                ->where('is_paid', false)
                ->pluck('id');
            PayrollItem::whereIn('payroll_id', $oldIds)->delete();
            Payroll::whereIn('id', $oldIds)->delete();

            // Potongan khusus hasil import sebelumnya (component potongan_khusus) — dibuang juga
            // supaya re-import tidak dobel, KECUALI milik karyawan yang sudah dibayar (dikunci).
            // Koperasi/Bon manual tidak disentuh.
            PayrollManualLine::where('payroll_period_id', $period->id)
                ->where('component', self::SOURCE_POTONGAN_KHUSUS)
                ->whereNotIn('user_id', $paidUserIds)
                ->delete();

            $created = 0;
            $skippedPaid = 0;

            foreach ($preview['matched'] as $m) {
                // Karyawan sudah dibayar di period ini — jangan ditimpa.
                if ($paidUserIds->contains($m['user_id'])) {
                    $skippedPaid++;
                    continue;
                }

                $row = $parsedByNip->get($m['nip_excel']);

                $payroll = Payroll::create([
                    'user_id' => $m['user_id'],
                    'payroll_period_id' => $period->id,
                    'gross_salary' => $row['gross'],
                    'total_deductions' => $row['deduction'],
                    'net_salary' => $row['net'],
                    'is_paid' => false,
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                    'import_meta' => [
                        'source' => 'excel_manual',
                        'unit' => $row['unit'],
                        'jabatan' => $row['jabatan'],
                        'status_karyawan' => $row['status_karyawan'],
                        'kelas' => $row['kelas'],
                        'tanggal_masuk' => $row['tanggal_masuk'],
                        'nama_excel' => $row['nama'],
                        'nip_excel' => $row['nip_excel'],
                        // net_excel SENGAJA tidak disimpan di sini — import_meta plaintext JSON,
                        // sedangkan itu angka gaji. Selisih net muncul sebagai warning pratinjau
                        // sebelum import. Nominal beneran (net_salary) tetap di kolom terenkripsi.
                        'imported_at' => now()->toIso8601String(),
                        'imported_by' => $actor->id,
                    ],
                ]);

                foreach (['earning' => $row['earnings'], 'deduction' => $row['deductions']] as $type => $lines) {
                    foreach ($lines as $line) {
                        PayrollItem::create([
                            'payroll_id' => $payroll->id,
                            'name' => $line['label'],
                            'type' => $type,
                            'quantity' => $line['quantity'] ?? 1,
                            'amount' => $line['amount'],
                            'note' => $line['note'] ?? null,
                            'source_type' => $line['source_type'],
                            'edited_manually' => true,
                        ]);

                        // Potongan khusus (AX) juga ditulis ke payroll_manual_lines — sumber
                        // kebenaran potongan manual per orang, senada Koperasi/Bon.
                        if (! empty($line['manual_line'])) {
                            PayrollManualLine::create([
                                'payroll_period_id' => $period->id,
                                'user_id' => $m['user_id'],
                                'component' => $line['source_type'],
                                'label' => $line['label'],
                                'amount' => $line['amount'],
                                'note' => 'Import Excel ' . now()->format('d/m/Y'),
                                'created_by' => $actor->id,
                            ]);
                        }
                    }
                }

                $created++;
            }

            if (! $period->payment_date && $preview['summary']['payment_date']) {
                $period->update(['payment_date' => $preview['summary']['payment_date']]);
            }

            return [
                'dibuat' => $created,
                'dilewati' => count($preview['unmatched']),
                'dilewati_dibayar' => $skippedPaid,
                'warnings' => $preview['warnings'],
            ];
        });
    }

    // ---------------------------------------------------------------------

    /**
     * Parse mentah — tidak sentuh DB. Skip section-header (E kosong), trailer ("JUMLAH"), Mitra.
     *
     * @return array{rows: list<array>, mitra_skipped: int, period_serial: mixed, payment_date: ?string}
     */
    private function parse(string $path, ?string $tunjanganKhususLabel = null): array
    {
        $tunjanganKhususLabel ??= self::TUNJANGAN_KHUSUS_LABEL_DEFAULT;

        $reader = IOFactory::createReaderForFile($path);
        // setReadDataOnly(false): banyak kolom (Gaji Pokok, Tunjangan Masa Kerja, subtotal, dst)
        // isinya RUMUS. Kalau file disimpan tanpa cache hasil rumus (LibreOffice / Google Sheets /
        // export tool), mode data-only mengembalikan teks rumus -> kebaca 0. Mode penuh + cell()
        // di bawah menghitung rumusnya sendiri. Filter cuma batasi BARIS (kolom dibaca semua
        // A..BA — rumus subtotal bisa mereferensi kolom mana saja, mis. N butuh M).
        $reader->setReadDataOnly(false);
        $maxCol = Coordinate::columnIndexFromString('BA');
        $reader->setReadFilter(new class($maxCol) implements IReadFilter {
            public function __construct(private int $maxCol) {}

            public function readCell($col, $row, $ws = ''): bool
            {
                return $row <= ManualPayrollExcelImporter::maxRow()
                    && Coordinate::columnIndexFromString($col) <= $this->maxCol;
            }
        });

        $sheet = $reader->load($path)->getActiveSheet();

        $cols = $this->resolveColumns($sheet);

        // Peta [huruf kolom => [source_type, label]] utk buildLines(). Komponen tetap SELALU
        // dibuat (walau Rp0) -> slip lengkap; tunjangan_khusus cuma kalau kolomnya ada.
        $earnMap = [];
        foreach ([
            'gaji_pokok' => null, 'tunjangan_jabatan' => null, 'insentif' => null,
            'tunjangan_masa_kerja' => null, 'jasa_pelayanan' => null, 'lembur' => null,
            'extra_shift' => null, 'tunjangan_khusus' => $tunjanganKhususLabel,
        ] as $st => $label) {
            if (! empty($cols[$st])) {
                $earnMap[$cols[$st]] = [$st, $label];
            }
        }
        $dedMap = [];
        foreach (['bpjs_kesehatan', 'bpjs_ketenagakerjaan', 'izin_terlambat_pulang_cepat', 'koperasi', 'bon'] as $st) {
            if (! empty($cols[$st])) {
                $dedMap[$cols[$st]] = [$st, null];
            }
        }
        if (! empty($cols['izin_cuti_gabung'])) {
            $dedMap[$cols['izin_cuti_gabung']] = ['izin_cuti_gabung', 'Potongan Izin & Cuti'];
        }
        if (! empty($cols['keterlambatan'])) {
            $dedMap[implode('+', (array) $cols['keterlambatan'])] = ['keterlambatan', null];
        }

        $periodSerial = $this->cell($sheet, 'B2');
        $rows = [];
        $mitra = 0;
        $unit = null;
        $paymentDate = null;

        for ($r = self::FIRST_DATA_ROW; $r <= self::MAX_ROW; $r++) {
            $b = trim((string) $this->cell($sheet, "B$r"));
            $e = trim((string) $this->cell($sheet, "E$r"));

            if ($b === '' && $e === '') {
                continue;
            }
            if (stripos($b, 'jumlah') === 0) {
                break; // trailer rekap
            }
            if ($e === '') {
                // section header: B = nama unit
                if ($b !== '' && ! is_numeric($b)) {
                    $unit = $b;
                }
                continue;
            }

            $status = trim((string) $this->cell($sheet, "K$r"));
            if (stripos($status, 'mitra') !== false) {
                $mitra++;
                continue;
            }

            $nipExcel = $e;
            $nip = $this->normalizeNip($e);

            $earnings = $this->buildLines($sheet, $r, $earnMap);
            $deductions = $this->buildLines($sheet, $r, $dedMap);

            // Jasa Unit -> 1 baris per jenis yang terisi (source_type `jasa_unit`, name = jenisnya).
            // Di slip dikelompokkan di bawah judul "Jasa Unit".
            foreach (self::JASA_UNIT_LABELS as $concept => $jenis) {
                if (empty($cols[$concept])) {
                    continue;
                }
                $v = $this->num($this->cell($sheet, $cols[$concept] . $r));
                if ($v > 0) {
                    $earnings[] = [
                        'source_type' => 'jasa_unit',
                        'label' => $jenis,
                        'amount' => round($v, 2),
                        'quantity' => 1,
                        'note' => null,
                    ];
                }
            }

            // Jumlah kejadian -> quantity + note baris terkait (tampil di slip "Nx/Bulan").
            $telatCount = 0;
            foreach ((array) ($cols['telat_count'] ?? []) as $c) {
                $telatCount += (int) $this->num($this->cell($sheet, $c . $r));
            }
            $izinCutiCount = ! empty($cols['izin_cuti_count'])
                ? (int) $this->num($this->cell($sheet, $cols['izin_cuti_count'] . $r))
                : 0;
            $this->tagCount($deductions, 'keterlambatan', $telatCount, $telatCount > 0 ? "{$telatCount}x/Bulan" : null);
            $this->tagCount($deductions, 'izin_cuti_gabung', $izinCutiCount, $izinCutiCount > 0 ? "{$izinCutiCount}x" : null);

            // Potongan khusus (hari efektif kontrak / cuti melahirkan) — kolomnya opsional,
            // baris cuma dibuat kalau kolom ADA & terisi > 0.
            $potonganKhusus = ! empty($cols['potongan_khusus'])
                ? $this->num($this->cell($sheet, $cols['potongan_khusus'] . $r))
                : 0.0;
            if ($potonganKhusus > 0) {
                $deductions[] = [
                    'source_type' => self::SOURCE_POTONGAN_KHUSUS,
                    'label' => 'Potongan Hari Kerja Efektif & Cuti Melahirkan',
                    'amount' => round($potonganKhusus, 2),
                    'quantity' => 1,
                    'note' => null,
                    'manual_line' => true, // ikut ditulis ke payroll_manual_lines saat import()
                ];
            }

            // Angka resmi ikut subtotal Excel, TIDAK dijumlah ulang dari komponen (rincian
            // komponen bisa saja tak persis menjumlah ke total Excel — itu urusan slip, bukan
            // total gaji). Fallback ke jumlah komponen kalau sel subtotal Excel kosong.
            $gross = $this->num($this->cell($sheet, $cols['gross'] . $r))
                ?: array_sum(array_column($earnings, 'amount'));
            $deduction = $this->num($this->cell($sheet, $cols['deduction'] . $r))
                ?: array_sum(array_column($deductions, 'amount'));
            $net = $this->num($this->cell($sheet, $cols['net'] . $r))
                ?: ($gross - $deduction);

            $paymentDate ??= $this->serialToDate($this->cell($sheet, "D$r"));

            $rows[] = [
                'row' => $r,
                'unit' => $unit,
                'nip' => $nip,
                'nip_excel' => $nipExcel,
                'nama' => $b,
                'jabatan' => trim((string) $this->cell($sheet, "I$r")) ?: null,
                'status_karyawan' => $status ?: null,
                'kelas' => $this->intOrNull($this->cell($sheet, "P$r")),
                'tanggal_masuk' => $this->serialToDate($this->cell($sheet, "L$r")),
                'earnings' => $earnings,
                'deductions' => $deductions,
                'gross' => $gross,
                'deduction' => $deduction,
                'net' => $net,
                'net_excel' => $net,
            ];
        }

        return [
            'rows' => $rows,
            'mitra_skipped' => $mitra,
            'period_serial' => $periodSerial,
            'payment_date' => $paymentDate,
        ];
    }

    /**
     * Petakan konsep -> huruf kolom dari TEKS HEADER baris 1 (fallback baris 2). Beda layout RS
     * (Barabai −2 kolom vs Banjarbaru) jadi tak masalah. Konsep wajib yg tak ketemu -> exception
     * (biar ketahuan dini, bukan diam-diam 0).
     *
     * @return array<string, string|list<string>|null>
     */
    private function resolveColumns($sheet): array
    {
        $headers = [];
        $maxCol = Coordinate::columnIndexFromString('BA');
        for ($i = 1; $i <= $maxCol; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $h = trim((string) $this->cell($sheet, $col . '1')) ?: trim((string) $this->cell($sheet, $col . '2'));
            if ($h !== '') {
                $headers[$col] = mb_strtolower(preg_replace('/\s+/', ' ', $h));
            }
        }

        $matches = function (string $needle) use ($headers): array {
            $out = [];
            foreach ($headers as $col => $h) {
                if (str_contains($h, $needle)) {
                    $out[] = $col;
                }
            }
            return $out;
        };

        $cols = [];

        foreach (self::HEADER_SIGNATURES as $concept => $needle) {
            $found = $matches($needle);
            if (! $found && ! in_array($concept, self::RESOLVE_OPTIONAL, true)) {
                throw new \RuntimeException("Kolom \"{$concept}\" (header memuat \"{$needle}\") tidak ditemukan — struktur Excel tidak dikenali.");
            }
            $cols[$concept] = $found[0] ?? null;
        }

        foreach (self::HEADER_SIGNATURES_MULTI as $concept => $needle) {
            $found = $matches($needle);
            if (! $found) {
                throw new \RuntimeException("Kolom \"{$concept}\" (header memuat \"{$needle}\") tidak ditemukan — struktur Excel tidak dikenali.");
            }
            $cols[$concept] = $found;
        }

        return $cols;
    }

    /** @return list<array{source_type:string,label:string,amount:float}> */
    private function buildLines($sheet, int $row, array $map): array
    {
        $out = [];
        foreach ($map as $colExpr => [$sourceType, $customLabel]) {
            $amount = 0.0;
            foreach (explode('+', $colExpr) as $col) {
                $amount += $this->num($this->cell($sheet, $col . $row));
            }
            // Komponen tetap (EARNINGS/DEDUCTIONS) SELALU dibuat walau Rp0 -> slip lengkap.
            // (Baris dinamis Jasa Unit per-jenis & potongan khusus AX tetap hanya kalau terisi.)
            $amount = max(0.0, $amount);
            $out[] = [
                'source_type' => $sourceType,
                'label' => $customLabel ?? (PayrollComponentEnum::tryFrom($sourceType)?->label() ?? ucfirst(str_replace('_', ' ', $sourceType))),
                'amount' => round($amount, 2),
                'quantity' => 1,
                'note' => null,
            ];
        }
        return $out;
    }

    /** Set quantity + note pada baris ber-source_type $type (kalau ada di $lines). */
    private function tagCount(array &$lines, string $type, int $count, ?string $note): void
    {
        if ($count <= 0) {
            return;
        }
        foreach ($lines as &$line) {
            if ($line['source_type'] === $type) {
                $line['quantity'] = $count;
                $line['note'] = $note;
            }
        }
    }

    /** Label baris Tunjangan Khusus untuk 1 perusahaan (hardcode by companies.code). */
    private function tunjanganKhususLabel(string $companyId): string
    {
        $code = \App\Models\Master\Companies::whereKey($companyId)->value('code');

        return self::TUNJANGAN_KHUSUS_LABEL[$code] ?? self::TUNJANGAN_KHUSUS_LABEL_DEFAULT;
    }

    private function resolveUser(string $nip): ?User
    {
        if ($nip === '') {
            return null;
        }
        $padded = str_pad($nip, 11, '0', STR_PAD_LEFT);

        return User::query()
            ->with('profile')
            ->where(fn ($q) => $q->where('nip', $nip)->orWhere('nip', $padded))
            ->first();
    }

    private function normalizeNip(string $raw): string
    {
        return preg_replace('/[^0-9]/', '', preg_replace('/^D[A-Z]-?/i', '', trim($raw)));
    }

    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    /**
     * Nilai sel dgn rumus dihitung (bukan teks rumusnya). Fallback berurutan kalau engine
     * PhpSpreadsheet gagal/ tak dukung fungsinya: cache hasil rumus lama -> teks mentah.
     */
    private function cell($sheet, string $coord): mixed
    {
        $c = $sheet->getCell($coord);

        if (! $c->isFormula()) {
            return $c->getValue();
        }

        try {
            return $c->getCalculatedValue();
        } catch (\Throwable) {
            return $c->getOldCalculatedValue() ?? $c->getValue();
        }
    }

    private function intOrNull(mixed $v): ?int
    {
        return preg_match('/\d+/', (string) $v, $m) ? (int) $m[0] : null;
    }

    private function serialToDate(mixed $v): ?string
    {
        if (is_numeric($v) && $v > 0) {
            return XlsDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        if (is_string($v) && $v !== '' && ($ts = strtotime($v))) {
            return date('Y-m-d', $ts);
        }
        return null;
    }

    public static function maxRow(): int
    {
        return self::MAX_ROW;
    }

    /**
     * Kolom komponen untuk tabel pratinjau — urut ikut slip, label pendek. Dipakai blade
     * halaman import buat header + baca $matched[i]['amounts'][key].
     *
     * @return array{earnings: array<string,string>, deductions: array<string,string>}
     */
    public static function previewColumns(): array
    {
        return [
            'earnings' => [
                'gaji_pokok'          => 'Gaji Pokok',
                'tunjangan_jabatan'   => 'Tunj. Jabatan',
                'insentif'            => 'Insentif',
                self::SOURCE_TUNJANGAN_KHUSUS => 'Tunj. Khusus',
                'tunjangan_masa_kerja' => 'Tunj. Masa Kerja',
                'jasa_pelayanan'      => 'Jasa Pelayanan',
                'jasa_unit'           => 'Jasa Unit',
                'lembur'              => 'Lembur',
                'extra_shift'         => 'Extra Shift',
            ],
            'deductions' => [
                'bpjs_kesehatan'      => 'BPJS Kes',
                'bpjs_ketenagakerjaan' => 'BPJS TK',
                'keterlambatan'       => 'Telat',
                'izin_terlambat_pulang_cepat' => 'Izin Jam Kerja',
                'izin_cuti_gabung'    => 'Izin & Cuti',
                'koperasi'            => 'Koperasi',
                'bon'                 => 'Bon',
                self::SOURCE_POTONGAN_KHUSUS => 'Pot. HK Efektif/Melahirkan',
            ],
        ];
    }
}
