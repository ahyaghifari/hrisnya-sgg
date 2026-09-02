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
    /** Kolom pendapatan: [kolom Excel (bisa "A+B" utk dijumlah) => [source_type, label]] */
    private const EARNINGS = [
        'S'       => ['gaji_pokok', null],
        'T+W'     => ['tunjangan_jabatan', null],
        'U'       => ['insentif', null],
        'V'       => ['tunjangan_khusus', null], // label per perusahaan, lihat TUNJANGAN_KHUSUS_LABEL
        'N'       => ['tunjangan_masa_kerja', null],
        'Y'       => ['jasa_pelayanan', null],
        // AA/AD/AE = jasa_unit, digabung jadi 1 baris "Jasa Unit (rincian, ...)" — lihat JASA_UNIT_COLS
        'AB'      => ['lembur', null],
        'AC'      => ['extra_shift', null],
    ];

    /** Kolom Jasa Unit -> nama jenis jasanya (untuk label "Jasa Unit (jenis1, jenis2)"). */
    private const JASA_UNIT_COLS = [
        'AA' => 'Tambahan Jasa',
        'AD' => 'Ambulans & Rujukan Pasien',
        'AE' => 'Paket Infus',
    ];

    /** Kolom potongan. */
    private const DEDUCTIONS = [
        'AG'      => ['bpjs_kesehatan', null],
        'AH'      => ['bpjs_ketenagakerjaan', null],
        'AJ+AL'   => ['keterlambatan', null],
        'AM'      => ['izin_terlambat_pulang_cepat', null],
        'AO'      => ['izin_cuti_gabung', 'Potongan Izin & Cuti'],
        'AP'      => ['koperasi', null],
        'AQ'      => ['bon', null],
    ];

    // Total diambil LANGSUNG dari subtotal Excel (bukan dijumlah ulang dari komponen) — mapping
    // komponen cuma buat rincian slip, angka resmi gross/potongan/net ikut Excel apa adanya.
    private const GROSS_COL = 'AF';             // JUMLAH PENDAPATAN
    private const DEDUCTION_COL = 'AR';         // JUMLAH POTONGAN
    private const NET_COL = 'AS';               // TOTAL GAJI YANG DITERIMA
    private const TELAT_COUNT_COLS = ['AI', 'AK'];   // jumlah kejadian telat -> quantity baris keterlambatan
    private const IZIN_CUTI_COUNT_COL = 'AN';        // JMLH (CUTI, IZIN, SAKIT) -> quantity baris izin_cuti_gabung
    private const POTONGAN_KHUSUS_COL = 'AX';        // nominal potongan khusus (kontrak/cuti melahirkan), opsional

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
     * Tulis Payroll + PayrollItem untuk baris yang cocok. Idempoten: baris Payroll period+company
     * yang is_paid=false dihapus & dibuat ulang. Kalau ada yang is_paid=true -> throw.
     *
     * @return array{dibuat: int, dilewati: int, warnings: list<array>}
     */
    public function import(string $path, PayrollPeriod $period, User $actor): array
    {
        $preview = $this->preview($path, $period->company_id);

        $paidCount = Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->where('is_paid', true)
            ->count();

        if ($paidCount > 0) {
            throw new \RuntimeException("Period ini sudah punya {$paidCount} payroll berstatus DIBAYAR. Hapus manual dulu sebelum import ulang.");
        }

        $parsedByNip = collect($this->parse($path, $this->tunjanganKhususLabel($period->company_id))['rows'])->keyBy('nip_excel');

        return DB::transaction(function () use ($preview, $parsedByNip, $period, $actor) {
            // Bersihkan payroll lama yang belum dibayar (+ item-nya) untuk period ini.
            $oldIds = Payroll::where('payroll_period_id', $period->id)->pluck('id');
            PayrollItem::whereIn('payroll_id', $oldIds)->delete();
            Payroll::whereIn('id', $oldIds)->delete();

            // Potongan khusus hasil import sebelumnya (component potongan_khusus) — dibuang juga
            // supaya re-import tidak dobel. Koperasi/Bon manual tidak disentuh.
            PayrollManualLine::where('payroll_period_id', $period->id)
                ->where('component', self::SOURCE_POTONGAN_KHUSUS)
                ->delete();

            $created = 0;

            foreach ($preview['matched'] as $m) {
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

            $earnings = $this->buildLines($sheet, $r, self::EARNINGS);
            $deductions = $this->buildLines($sheet, $r, self::DEDUCTIONS);

            // Label "Tunjangan Khusus" (kolom V) beda per perusahaan.
            foreach ($earnings as &$e2) {
                if ($e2['source_type'] === self::SOURCE_TUNJANGAN_KHUSUS) {
                    $e2['label'] = $tunjanganKhususLabel;
                }
            }
            unset($e2);

            // Jasa Unit (AA/AD/AE) -> 1 baris per jenis yang terisi (source_type sama `jasa_unit`,
            // name = jenisnya). Di slip dikelompokkan di bawah judul "Jasa Unit".
            foreach (self::JASA_UNIT_COLS as $col => $jenis) {
                $v = $this->num($this->cell($sheet, $col . $r));
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
            foreach (self::TELAT_COUNT_COLS as $c) {
                $telatCount += (int) $this->num($this->cell($sheet, $c . $r));
            }
            $izinCutiCount = (int) $this->num($this->cell($sheet, self::IZIN_CUTI_COUNT_COL . $r));
            $this->tagCount($deductions, 'keterlambatan', $telatCount, $telatCount > 0 ? "{$telatCount}x/Bulan" : null);
            $this->tagCount($deductions, 'izin_cuti_gabung', $izinCutiCount, $izinCutiCount > 0 ? "{$izinCutiCount}x" : null);

            // Potongan khusus (AX) — hanya kalau terisi > 0.
            $potonganKhusus = $this->num($this->cell($sheet, self::POTONGAN_KHUSUS_COL . $r));
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
            $gross = $this->num($this->cell($sheet, self::GROSS_COL . $r))
                ?: array_sum(array_column($earnings, 'amount'));
            $deduction = $this->num($this->cell($sheet, self::DEDUCTION_COL . $r))
                ?: array_sum(array_column($deductions, 'amount'));
            $net = $this->num($this->cell($sheet, self::NET_COL . $r))
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
