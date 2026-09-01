<?php
/**
 * Bandingkan file gaji Excel vs export "Karyawan" HRIS — JABATAN, KELAS, TANGGAL MASUK.
 * Murni file-vs-file, TIDAK menyentuh DB.
 *
 *   php scripts/compare_payroll_vs_hris.php "knowledge/gaji-agustus.xlsx" "knowledge/data-karyawan-20260901-083750.xlsx"
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

$gajiPath = $argv[1] ?? 'knowledge/gaji-agustus.xlsx';
$hrisPath = $argv[2] ?? 'knowledge/data-karyawan-20260901-083750.xlsx';
foreach ([$gajiPath, $hrisPath] as $p) {
    if (! is_file($p)) { fwrite(STDERR, "File tidak ada: $p\n"); exit(1); }
}

$normNip   = fn (string $s) => preg_replace('/[^0-9]/', '', preg_replace('/^D[A-Z]-?/i', '', trim($s)));
$pad       = fn (string $s) => str_pad($s, 11, '0', STR_PAD_LEFT);
$normText  = fn (?string $s) => trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $s)));
$normJab   = function (?string $s) {
    $s = mb_strtolower(trim((string) $s));
    $s = str_replace(['penanggung jawab', 'p.j.', 'pj.', 'p j'], 'pj', $s);
    $s = str_replace(['lantai', 'lt.', 'lt '], 'lt', $s);
    $s = preg_replace('/[^a-z0-9 ]/', ' ', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return $s;
};

// ---------- baca file gaji ----------
$r = IOFactory::createReaderForFile($gajiPath);
$r->setReadDataOnly(true);
$r->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
    public function readCell($col, $row, $ws = ''): bool {
        return $row <= 560 && in_array($col, ['B','E','I','K','L','P'], true);
    }
});
$gs = $r->load($gajiPath)->getActiveSheet();

$gaji = [];       // nip => [nama, jabatan, status, kelas, tglMasuk(Y-m-d|null)]
$gajiMitra = 0;
for ($row = 4; $row <= 560; $row++) {
    $b = trim((string) $gs->getCell("B$row")->getValue());
    $e = trim((string) $gs->getCell("E$row")->getValue());
    $k = trim((string) $gs->getCell("K$row")->getValue());
    if ($b === '' && $e === '') continue;
    if (stripos($b, 'jumlah') === 0) break;          // trailer
    if ($e === '') continue;                          // section header
    if (stripos($k, 'mitra') !== false) { $gajiMitra++; continue; }

    $lRaw = $gs->getCell("L$row")->getValue();
    $tgl = null;
    if (is_numeric($lRaw) && $lRaw > 0) {
        $tgl = XlsDate::excelToDateTimeObject((float) $lRaw)->format('Y-m-d');
    } elseif (is_string($lRaw) && $lRaw !== '') {
        $ts = strtotime($lRaw);
        if ($ts) $tgl = date('Y-m-d', $ts);
    }

    $pRaw = trim((string) $gs->getCell("P$row")->getValue());
    $kelas = preg_match('/\d+/', $pRaw, $m) ? (int) $m[0] : null;

    $nip = $normNip($e);
    $gaji[$nip] = [
        'nama'     => $b,
        'jabatan'  => trim((string) $gs->getCell("I$row")->getValue()),
        'status'   => $k,
        'kelas'    => $kelas,
        'tgl'      => $tgl,
        'row'      => $row,
    ];
}

// ---------- baca export HRIS (kolom auto-detect dari header — layout export berubah-ubah) ----------
$r2 = IOFactory::createReaderForFile($hrisPath);
$r2->setReadDataOnly(true);
$hs = $r2->load($hrisPath)->getActiveSheet();
$hiRow = $hs->getHighestDataRow();
$hiCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($hs->getHighestDataColumn());

$col = [];
for ($c = 1; $c <= $hiCol; $c++) {
    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    $h = mb_strtolower(trim((string) $hs->getCell($letter . '1')->getValue()));
    if ($h === '') continue;
    if (str_contains($h, 'nip')) $col['nip'] = $letter;
    elseif (str_contains($h, 'nama')) $col['nama'] = $letter;
    elseif (str_contains($h, 'jabatan')) $col['jabatan'] = $letter;
    elseif (str_contains($h, 'kelas')) $col['kelas'] = $letter;
    elseif (str_contains($h, 'tanggal') && (str_contains($h, 'gabung') || str_contains($h, 'masuk'))) $col['tgl'] = $letter;
}
foreach (['nip','nama','jabatan','kelas','tgl'] as $need) {
    if (! isset($col[$need])) { fwrite(STDERR, "Header '$need' tak ketemu di export HRIS. Header baris 1: " . $hs->getHighestDataColumn() . "\n"); exit(1); }
}
fwrite(STDERR, "HRIS kolom: nip={$col['nip']} nama={$col['nama']} jabatan={$col['jabatan']} kelas={$col['kelas']} tgl={$col['tgl']}\n");

$hris = [];
for ($row = 2; $row <= $hiRow; $row++) {
    $bnip = trim((string) $hs->getCell($col['nip'] . $row)->getValue());
    $cnam = trim((string) $hs->getCell($col['nama'] . $row)->getValue());
    if ($bnip === '' || $cnam === '') continue;       // section header / kosong

    $fRaw = trim((string) $hs->getCell($col['kelas'] . $row)->getValue());
    $kelas = preg_match('/\d+/', $fRaw, $m) ? (int) $m[0] : null;

    $hRaw = $hs->getCell($col['tgl'] . $row)->getValue();
    $tgl = null;
    if (is_numeric($hRaw) && $hRaw > 0) {
        $tgl = XlsDate::excelToDateTimeObject((float) $hRaw)->format('Y-m-d');
    } elseif (is_string($hRaw) && $hRaw !== '') {
        $ts = strtotime($hRaw);
        if ($ts) $tgl = date('Y-m-d', $ts);
    }

    $nip = $normNip($bnip);
    $hris[$nip] = [
        'nama'    => $cnam,
        'jabatan' => trim((string) $hs->getCell($col['jabatan'] . $row)->getValue()),
        'kelas'   => $kelas,
        'tgl'     => $tgl,
        'row'     => $row,
    ];
}

// ---------- cocokkan (exact -> zero-pad 11) ----------
$hrisByPad = [];
foreach ($hris as $nip => $v) $hrisByPad[$pad($nip)] = $nip + 0 === 0 ? $nip : $nip; // keep key
$resolve = function (string $nip) use ($hris, $pad) {
    if (isset($hris[$nip])) return $nip;
    $p = $pad($nip);
    if (isset($hris[$p])) return $p;
    foreach ($hris as $k => $_) if ($pad($k) === $p) return $k;
    return null;
};

$matched = [];
$unmatched = [];
foreach ($gaji as $nip => $g) {
    $h = $resolve($nip);
    if ($h === null) { $unmatched[$nip] = $g; continue; }
    $matched[$nip] = ['g' => $g, 'h' => $hris[$h], 'hnip' => $h];
}

// ---------- JABATAN ----------
$jabIdentik = 0; $jabCosmetic = []; $jabKonflik = [];
foreach ($matched as $nip => $mm) {
    $gi = $normJab($mm['g']['jabatan']);
    $hi = $normJab($mm['h']['jabatan']);
    if ($gi === '' && $hi === '') { $jabIdentik++; continue; }
    if ($gi === $hi) { $jabIdentik++; continue; }

    $row = sprintf("%-13s | %-30s | XLS: %-45s | HRIS: %s",
        $nip, mb_substr($mm['g']['nama'], 0, 30), mb_substr($mm['g']['jabatan'] ?: '(kosong)', 0, 45), $mm['h']['jabatan'] ?: '(kosong)');

    // cosmetic kalau salah satu substring lain, atau overlap token >= 60%
    $cosmetic = false;
    if ($gi !== '' && $hi !== '' && (str_contains($gi, $hi) || str_contains($hi, $gi))) {
        $cosmetic = true;
    } else {
        $tg = array_filter(explode(' ', $gi)); $th = array_filter(explode(' ', $hi));
        $inter = count(array_intersect($tg, $th));
        $uni = count(array_unique(array_merge($tg, $th)));
        if ($uni > 0 && $inter / $uni >= 0.6) $cosmetic = true;
    }
    if ($cosmetic) $jabCosmetic[] = $row; else $jabKonflik[] = $row;
}

// ---------- KELAS ----------
$kelasOk = 0; $kelasBeda = []; $kelasKosong = [];
foreach ($matched as $nip => $mm) {
    $gk = $mm['g']['kelas']; $hk = $mm['h']['kelas'];
    if ($gk === null || $hk === null) {
        $kelasKosong[] = sprintf("%-13s | %-30s | XLS:%s HRIS:%s", $nip, mb_substr($mm['g']['nama'],0,30), $gk ?? '-', $hk ?? '-');
        continue;
    }
    if ($gk === $hk) { $kelasOk++; continue; }
    $kelasBeda[] = sprintf("%-13s | %-30s | XLS:%-3s HRIS:%-3s (selisih %+d)", $nip, mb_substr($mm['g']['nama'],0,30), $gk, $hk, $gk - $hk);
}

// ---------- TANGGAL MASUK ----------
$tglOk = 0; $tglBeda = []; $tglKosong = [];
foreach ($matched as $nip => $mm) {
    $gt = $mm['g']['tgl']; $ht = $mm['h']['tgl'];
    if (! $gt || ! $ht) {
        $tglKosong[] = sprintf("%-13s | %-30s | XLS:%s HRIS:%s", $nip, mb_substr($mm['g']['nama'],0,30), $gt ?: '-', $ht ?: '-');
        continue;
    }
    if ($gt === $ht) { $tglOk++; continue; }
    $dg = new DateTime($gt); $dh = new DateTime($ht);
    $days = (int) $dg->diff($dh)->format('%r%a');
    $sameMonth = substr($gt, 0, 7) === substr($ht, 0, 7);
    $tglBeda[] = sprintf("%-13s | %-30s | XLS:%s HRIS:%s | selisih %+d hari%s",
        $nip, mb_substr($mm['g']['nama'],0,30), $gt, $ht, $days, $sameMonth ? '  (bulan sama)' : '');
}

// ---------- OUTPUT ----------
$line = str_repeat('=', 92);
echo "$line\nRINGKASAN\n$line\n";
echo "Baris gaji (non-Mitra) : " . count($gaji) . "   |  Mitra dilewati: $gajiMitra\n";
echo "Baris HRIS export       : " . count($hris) . "\n";
echo "Cocok by NIP            : " . count($matched) . "   |  Tak cocok: " . count($unmatched) . "\n\n";

echo "JABATAN  : identik $jabIdentik  |  cosmetic " . count($jabCosmetic) . "  |  konflik " . count($jabKonflik) . "\n";
echo "KELAS    : sama $kelasOk  |  beda " . count($kelasBeda) . "  |  ada yang kosong " . count($kelasKosong) . "\n";
echo "TGL MASUK: sama $tglOk  |  beda " . count($tglBeda) . "  |  ada yang kosong " . count($tglKosong) . "\n\n";

echo "$line\nJABATAN — KONFLIK NYATA (" . count($jabKonflik) . ")\n$line\n";
foreach ($jabKonflik as $x) echo "$x\n";

echo "\n$line\nJABATAN — COSMETIC (" . count($jabCosmetic) . ")\n$line\n";
foreach ($jabCosmetic as $x) echo "$x\n";

echo "\n$line\nKELAS — BEDA (" . count($kelasBeda) . ")\n$line\n";
foreach ($kelasBeda as $x) echo "$x\n";
echo "\nKELAS — ADA KOSONG (" . count($kelasKosong) . ")\n";
foreach ($kelasKosong as $x) echo "$x\n";

echo "\n$line\nTANGGAL MASUK — BEDA (" . count($tglBeda) . ")  [NIP = DDMMYYYY+seq]\n$line\n";
// NIP -> tanggal (DD MM YYYY dari 8 digit awal setelah zero-pad 11)
$nipDate = function (string $nip): ?string {
    $n = str_pad(preg_replace('/[^0-9]/', '', $nip), 11, '0', STR_PAD_LEFT);
    if (strlen($n) < 8) return null;
    $d = (int) substr($n, 0, 2); $mo = (int) substr($n, 2, 2); $y = (int) substr($n, 4, 4);
    if ($d < 1 || $d > 31 || $mo < 1 || $mo > 12 || $y < 2000 || $y > 2035) return null;
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
};
$xlsMatchNip = 0; $hrisMatchNip = 0; $noneMatchNip = 0; $nipUnparsed = 0;
foreach ($matched as $nip => $mm) {
    $gt = $mm['g']['tgl']; $ht = $mm['h']['tgl'];
    if (! $gt || ! $ht || $gt === $ht) continue;
    $nd = $nipDate($nip);
    $tag = $nd === null ? '  [NIP tak ter-parse]'
        : ($nd === $gt ? '  >> XLS cocok NIP'
        : ($nd === $ht ? '  >> HRIS cocok NIP'
        : "  >> keduanya beda (NIP=$nd)"));
    if ($nd === null) $nipUnparsed++;
    elseif ($nd === $gt) $xlsMatchNip++;
    elseif ($nd === $ht) $hrisMatchNip++;
    else $noneMatchNip++;
    $dg = new DateTime($gt); $dh = new DateTime($ht);
    $days = (int) $dg->diff($dh)->format('%r%a');
    printf("%-13s | %-30s | XLS:%s HRIS:%s | %+d hari%s\n",
        $nip, mb_substr($mm['g']['nama'],0,30), $gt, $ht, $days, $tag);
}
echo "\n  -> XLS cocok NIP: $xlsMatchNip   HRIS cocok NIP: $hrisMatchNip   keduanya salah: $noneMatchNip   NIP tak ter-parse: $nipUnparsed\n";
echo "\nTANGGAL MASUK — ADA KOSONG (" . count($tglKosong) . ")\n";
foreach ($tglKosong as $x) echo "$x\n";

echo "\n$line\nNIP GAJI TAK COCOK DI HRIS EXPORT (" . count($unmatched) . ")\n$line\n";
foreach ($unmatched as $nip => $g) echo sprintf("%-13s | %-30s | jab XLS: %s\n", $nip, mb_substr($g['nama'],0,30), $g['jabatan']);
