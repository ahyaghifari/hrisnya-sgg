<?php
/**
 * Bikin file uji `knowledge/gaji-test.xlsx` — struktur & header PERSIS `gaji-agustus.xlsx`,
 * NIP asli dari export karyawan, angka DIISI (bukan blank) supaya importer bisa langsung dites.
 *
 *   php scripts/make_gaji_test_fixture.php
 *
 * Skenario yang dicakup (biar sekali import kelihatan semua fitur):
 *  - Section header per unit (E kosong) + trailer "JUMLAH"
 *  - 3 baris Mitra (dokter) -> importer SKIP
 *  - 2 NIP yang tidak ada di HRIS -> masuk daftar "tak ketemu" (tidak diimport)
 *  - T+W digabung jadi Tunjangan Jabatan ; AJ+AL digabung jadi Keterlambatan
 *  - AI/AK (jumlah telat) -> quantity + note "Nx/Bulan" ; AN -> quantity baris Izin&Cuti
 *  - V (Tunjangan Khusus) di 2 orang ; AA/AD/AE (Jasa Unit) 1-3 jenis
 *  - AX (Potongan Hari Kerja Efektif & Cuti Melahirkan) di 1 orang + catatan AZ
 *  - Koperasi/Bon ; 2 baris dengan AS sengaja beda -> warning "net_beda"
 *  - Rp0 di banyak kolom -> baris PayrollItem tidak dibuat
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

$SRC   = __DIR__ . '/../knowledge/gaji-agustus.xlsx';
$OUT   = __DIR__ . '/../knowledge/gaji-test.xlsx';
$SERIAL_PERIODE = 46265;                       // sama seperti file asli (B2)
$SERIAL_BAYAR   = (int) XlsDate::PHPToExcel(new DateTime('2026-09-01'));

// ---- ambil header baris 1 dari file asli (persis) ----
$r = IOFactory::createReaderForFile($SRC);
$r->setReadDataOnly(true);
$srcSheet = $r->load($SRC)->getActiveSheet();
$headers = [];
foreach ($srcSheet->getRowIterator(1, 1) as $row) {
    foreach ($row->getCellIterator() as $c) {
        $v = $c->getValue();
        if ($v !== null && $v !== '') {
            $headers[$c->getColumn()] = $v;
        }
    }
}

// ---- data uji: [nip, nama, jabatan, kelas, status, dob, opsi...] ----
// status: 'Karyawan Tetap' | 'Karyawan Kontrak' | 'Karyawan Orientasi' | 'Mitra'
$U = fn (string $unit) => ['__section' => $unit];

$rows = [
    $U('MANAJEMEN'),
    ['nip' => '01032018118', 'nama' => 'Mirajjudin Alvianor, S.ST', 'jab' => 'Kabag Administrasi dan Umum', 'kelas' => 9, 'status' => 'Karyawan Tetap', 'dob' => '1988-03-01', 'kepala' => true, 'masa' => 'lama'],
    ['nip' => '12062019142', 'nama' => 'dr. Abdul Latif, M.M',        'jab' => 'Kabid Pelayanan Medik & Keperawatan', 'kelas' => 9, 'status' => 'Karyawan Tetap', 'dob' => '1985-06-12', 'kepala' => true, 'masa' => 'lama', 'tunj_khusus' => 750_000],
    ['nip' => '01122017099', 'nama' => 'Evi Febriana Pratiwi, S. Kep', 'jab' => 'Kasubag. Hukum, Humas & Promkes', 'kelas' => 5, 'status' => 'Karyawan Tetap', 'dob' => '1990-12-01', 'kepala' => true, 'masa' => 'lama', 'izin_cuti' => [3, 300_000]],
    ['nip' => '19112020310', 'nama' => 'Yanwar Rahman, S.KM',          'jab' => 'Digital Marketing', 'kelas' => 4, 'status' => 'Karyawan Tetap', 'dob' => '1994-11-19', 'masa' => 'sedang'],

    $U('HUMAS & PROMKES'),
    ['nip' => '27022023478', 'nama' => 'Jenny Maulidya Rohima, S. Kep', 'jab' => 'Promkes', 'kelas' => 3, 'status' => 'Karyawan Tetap', 'dob' => '1997-02-27', 'masa' => 'baru', 'telat' => [2, 25_000, 1, 10_000]],
    ['nip' => '20032023483', 'nama' => 'Yeni Noor Cahyani, S.Pd',       'jab' => 'Promkes', 'kelas' => 3, 'status' => 'Karyawan Tetap', 'dob' => '1996-03-20', 'masa' => 'baru', 'pot_khusus' => [1_200_000, 'Cuti Melahirkan mulai 01/08/2026 s.d 29/10/2026 - sesuaikan gaji']],

    $U('IPSRS & TEKNIK UMUM'),
    ['nip' => '05122023588', 'nama' => 'Abdurrahman Musyaffa, S.T.',   'jab' => 'Kepala IPSRS', 'kelas' => 5, 'status' => 'Karyawan Tetap', 'dob' => '1993-12-05', 'kepala' => true, 'masa' => 'baru'],
    ['nip' => '03012017051', 'nama' => 'Muhammad Firman Setiawan',      'jab' => 'Koordinator Tekhnik Umum', 'kelas' => 4, 'status' => 'Karyawan Tetap', 'dob' => '1989-01-03', 'kepala' => true, 'masa' => 'lama', 'lembur' => 120_000],
    ['nip' => '03012017052', 'nama' => 'Fahruraji',                     'jab' => 'Teknisi Umum', 'kelas' => 3, 'status' => 'Karyawan Tetap', 'dob' => '1990-01-03', 'masa' => 'lama', 'telat' => [3, 30_000, 2, 15_000]],
    ['nip' => '15032021347', 'nama' => 'Achmad Rachmadani',             'jab' => 'Teknisi Umum', 'kelas' => 3, 'status' => 'Karyawan Tetap', 'dob' => '1992-03-15', 'masa' => 'sedang', 'koperasi' => 200_000, 'bon' => 500_000],
    ['nip' => '12062023511', 'nama' => 'Taufan Aditya Rachman',         'jab' => 'Teknisi Umum', 'kelas' => 3, 'status' => 'Karyawan Kontrak', 'dob' => '1998-06-12', 'masa' => 'baru', 'jasa_unit' => ['AA' => 100_000, 'AE' => 75_000]],
    ['nip' => '01072024642', 'nama' => 'Muhammad Rezeki, A.Md.Tem.',    'jab' => 'Koordinator Elektromedik', 'kelas' => 3, 'status' => 'Karyawan Kontrak', 'dob' => '1999-07-01', 'kepala' => true, 'masa' => 'baru'],
    ['nip' => '08012025699', 'nama' => 'Muhammad Iqbal Yasir',          'jab' => 'Teknisi Umum', 'kelas' => 2, 'status' => 'Karyawan Kontrak', 'dob' => '2000-01-08', 'masa' => 'baru', 'as_offset' => -12_345],
    ['nip' => '01032024611', 'nama' => 'Hendri',                        'jab' => 'Teknisi Umum', 'kelas' => 3, 'status' => 'Karyawan Tetap', 'dob' => '1991-03-01', 'masa' => 'baru', 'extra_shift' => 200_000],

    $U('KEAMANAN'),
    ['nip' => '16012021333', 'nama' => 'Wahyu Romadoni',               'jab' => 'Kepala Instalasi Keamanan', 'kelas' => 4, 'status' => 'Karyawan Tetap', 'dob' => '1990-01-16', 'kepala' => true, 'masa' => 'sedang', 'tunj_khusus' => 500_000],
    ['nip' => '10042018122', 'nama' => 'Sunarmo',                      'jab' => 'Satpam', 'kelas' => 3, 'status' => 'Karyawan Tetap', 'dob' => '1987-04-10', 'masa' => 'lama', 'lembur' => 150_000, 'jasa_unit' => ['AD' => 150_000]],
    ['nip' => '22082023537', 'nama' => 'Muhammad Solihin',             'jab' => 'Satpam', 'kelas' => 3, 'status' => 'Karyawan Tetap', 'dob' => '1995-08-22', 'masa' => 'baru', 'jasa_unit' => ['AA' => 50_000, 'AD' => 120_000, 'AE' => 60_000]],
    ['nip' => '22082023538', 'nama' => 'Feisal Ali Firdaus, S.Pd.',    'jab' => 'Satpam', 'kelas' => 3, 'status' => 'Karyawan Tetap', 'dob' => '1996-08-22', 'masa' => 'baru', 'as_offset' => 50_000, 'izin_terlambat' => 40_000],

    $U('IGD & DOKTER'),
    ['nip' => 'DU-08012020006', 'nama' => 'dr. Shinta Putri Fidayanti', 'jab' => 'Dokter IGD', 'kelas' => null, 'status' => 'Mitra', 'dob' => '1990-01-08', 'gp' => 8_000_000],
    ['nip' => 'DG-02022017064', 'nama' => 'drg. Harinda Wahyu K.',      'jab' => 'Dokter Gigi', 'kelas' => null, 'status' => 'Mitra', 'dob' => '1988-02-02', 'gp' => 7_000_000],
    ['nip' => '01042026997',    'nama' => 'Sudrajad Suef',              'jab' => 'Teknisi Umum', 'kelas' => 2, 'status' => 'Mitra', 'dob' => '2001-04-01', 'gp' => 1_500_000],
    ['nip' => '99999999999',    'nama' => 'Karyawan Fiktif Test',       'jab' => 'Staf Uji', 'kelas' => 3, 'status' => 'Karyawan Kontrak', 'dob' => '1995-01-01'],
    ['nip' => '02022023502',    'nama' => 'Rachmawati Eka Putri Kesuma', 'jab' => 'Perawat (NIP salah ketik di Excel)', 'kelas' => 3, 'status' => 'Karyawan Tetap', 'dob' => '1996-06-02'],
];

// ---- generator angka ----
$serial = fn (string $d) => (int) XlsDate::PHPToExcel(new DateTime($d));
$nipDate = function (string $nip): ?string {
    $n = preg_replace('/\D/', '', $nip);
    if (strlen($n) < 8) return null;
    [$d, $mo, $y] = [(int) substr($n, 0, 2), (int) substr($n, 2, 2), (int) substr($n, 4, 4)];
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
};

$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Gaji Skema Baru (JE)');

foreach ($headers as $col => $text) {
    $sheet->setCellValue($col . '1', $text);
}
$sheet->setCellValue('B2', $SERIAL_PERIODE);

$r = 3;
$no = 0;
foreach ($rows as $row) {
    if (isset($row['__section'])) {
        $sheet->setCellValue('B' . $r, $row['__section']);   // E kosong -> section header
        $r++;
        continue;
    }

    $kelas  = $row['kelas'];
    $isMitra = $row['status'] === 'Mitra';

    // pendapatan
    $S = $row['gp'] ?? ($kelas ? 2_000_000 + $kelas * 250_000 : 0);
    $T = ! empty($row['kepala']) ? 600_000 : 0;
    $W = $isMitra ? 0 : 200_000;
    $U_ = $isMitra ? 0 : 300_000;
    $V = $row['tunj_khusus'] ?? 0;
    $N = $isMitra ? 0 : match ($row['masa'] ?? 'baru') { 'lama' => 150_000, 'sedang' => 50_000, default => 0 };
    $Y = $isMitra ? 0 : 400_000;
    $ju = $row['jasa_unit'] ?? [];
    $AA = $ju['AA'] ?? 0;
    $AD = $ju['AD'] ?? 0;
    $AE = $ju['AE'] ?? 0;
    $AB = $row['lembur'] ?? 0;
    $AC = $row['extra_shift'] ?? 0;

    // potongan
    $AG = $isMitra ? 0 : (int) round($S * 0.01);
    $AH = $isMitra ? 0 : (int) round($S * 0.02);
    [$AI, $AJ, $AK, $AL] = $row['telat'] ?? [0, 0, 0, 0];
    $AM = $row['izin_terlambat'] ?? 0;
    [$AN, $AO] = $row['izin_cuti'] ?? [0, 0];
    $AP = $row['koperasi'] ?? 0;
    $AQ = $row['bon'] ?? 0;
    [$AX, $AZ] = $row['pot_khusus'] ?? [0, null];

    // subtotal (importer abaikan X/Z/AF/AR; AS = pembanding net)
    $AF = $S + $T + $U_ + $V + $W + $N + $Y + $AA + $AB + $AC + $AD + $AE;
    $AR = $AG + $AH + $AJ + $AL + $AM + $AO + $AP + $AQ + $AX;
    $AS = $AF - $AR + ($row['as_offset'] ?? 0);   // as_offset -> sengaja bikin "net_beda"

    $no++;
    $put = fn (string $col, $val) => ($val === 0 || $val === null) ? null : $sheet->setCellValue($col . $r, $val);

    $sheet->setCellValue('A' . $r, $no);
    $sheet->setCellValue('B' . $r, $row['nama']);
    $sheet->setCellValue('C' . $r, $SERIAL_PERIODE);
    $sheet->setCellValue('D' . $r, $SERIAL_BAYAR);
    $sheet->setCellValueExplicit('E' . $r, $row['nip'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('F' . $r, '63710' . str_pad((string) $no, 11, '0', STR_PAD_LEFT)); // NIK dummy
    $sheet->setCellValue('G' . $r, 'Aktif');
    $sheet->setCellValue('H' . $r, $serial($row['dob']));
    $sheet->setCellValue('I' . $r, $row['jab']);
    $sheet->setCellValue('K' . $r, $row['status']);
    if ($tm = $nipDate($row['nip'])) {
        $sheet->setCellValue('L' . $r, $serial($tm));
    }
    if ($kelas) {
        $sheet->setCellValue('P' . $r, $kelas);
    }

    foreach ([
        'S' => $S, 'T' => $T, 'U' => $U_, 'V' => $V, 'W' => $W, 'X' => $S + $T + $U_ + $V + $W,
        'N' => $N, 'Y' => $Y, 'Z' => $S + $T + $U_ + $V + $W + $Y,
        'AA' => $AA, 'AB' => $AB, 'AC' => $AC, 'AD' => $AD, 'AE' => $AE, 'AF' => $AF,
        'AG' => $AG, 'AH' => $AH, 'AI' => $AI, 'AJ' => $AJ, 'AK' => $AK, 'AL' => $AL,
        'AM' => $AM, 'AN' => $AN, 'AO' => $AO, 'AP' => $AP, 'AQ' => $AQ, 'AR' => $AR, 'AS' => $AS,
        'AX' => $AX,
    ] as $col => $val) {
        $put($col, $val);
    }

    $sheet->setCellValueExplicit('AT' . $r, '71' . str_pad((string) $no, 8, '0', STR_PAD_LEFT), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('AU' . $r, mb_strtoupper($row['nama']));
    if ($AZ) {
        $sheet->setCellValue('AZ' . $r, $AZ);
    }

    $r++;
}

// trailer
$sheet->setCellValue('B' . $r, 'JUMLAH');
$sheet->setCellValue('S' . $r, '=SUM(S3:S' . ($r - 1) . ')');

(new Xlsx($ss))->save($OUT);

echo "OK -> $OUT\n";
echo "Baris data: " . ($r - 4) . " (termasuk section header & trailer di atas)\n";
echo "Cek cepat: 3 Mitra di-skip, 2 NIP tak ketemu (99999999999, 02022023502), 2 net-beda (Iqbal -12.345, Feisal +50.000), 1 potongan khusus (Yeni).\n";
