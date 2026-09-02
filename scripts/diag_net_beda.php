<?php
/**
 * Diagnosa kenapa banyak "net_beda" di import payroll manual. READ-ONLY, tidak sentuh DB.
 *
 *   php scripts/diag_net_beda.php "path/ke/gaji-asli.xlsx"
 *
 * Untuk tiap baris data (non-Mitra): bandingkan net hasil mapping importer vs kolom AS Excel,
 * lalu tunjukkan selisihnya datang dari mana — kolom mana yang punya nilai tapi TIDAK dibaca
 * importer, atau apakah subtotal AF/AR Excel sendiri sudah beda dari jumlah komponennya.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = $argv[1] ?? null;
if (! $path || ! is_file($path)) {
    fwrite(STDERR, "Usage: php scripts/diag_net_beda.php <xlsx>\n");
    exit(1);
}

// mapping importer (samakan dgn ManualPayrollExcelImporter)
$EARN = ['S', 'T', 'W', 'U', 'V', 'N', 'Y', 'AA', 'AD', 'AE', 'AB', 'AC'];
$DED  = ['AG', 'AH', 'AJ', 'AL', 'AM', 'AO', 'AP', 'AQ'];
$AX   = 'AX'; // potongan khusus (deduction juga)
$MAPPED_MONEY = array_merge($EARN, $DED, [$AX]);

// kolom "bukan uang" / subtotal yang MEMANG diabaikan — tak dihitung sbg "unmapped mencurigakan"
$IGNORE = ['A','B','C','D','E','F','G','H','I','J','K','L','M','O','P','Q','R','X','Z','AF','AR','AS','AT','AU','AV','AW','AY','AZ','AI','AK','AN'];

$r = IOFactory::createReaderForFile($path);
$r->setReadDataOnly(true);
$s = $r->load($path)->getActiveSheet();

$num = fn ($v) => is_numeric($v) ? (float) $v : 0.0;
$cell = fn ($c, $row) => $num($s->getCell($c . $row)->getValue());

$hiCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($s->getHighestDataColumn());
$allCols = [];
for ($i = 1; $i <= min($hiCol, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('AY')); $i++) {
    $allCols[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
}
$unmappedCandidates = array_values(array_diff($allCols, $MAPPED_MONEY, $IGNORE));

$rows = 0; $beda = 0; $sumDiff = 0; $pos = 0; $neg = 0;
$byUnmappedCol = [];   // kolom => berapa kali nilainya "menjelaskan" selisih
$grossMismatch = 0; $dedMismatch = 0;
$samples = [];

for ($row = 3; $row <= 800; $row++) {
    $b = trim((string) $s->getCell("B$row")->getValue());
    $e = trim((string) $s->getCell("E$row")->getValue());
    if ($b === '' && $e === '') continue;
    if (stripos($b, 'jumlah') === 0) break;
    if ($e === '') continue; // section header
    if (stripos((string) $s->getCell("K$row")->getValue(), 'mitra') !== false) continue;

    $rows++;

    $gross = 0.0; foreach ($EARN as $c) $gross += $cell($c, $row);
    $ded = 0.0;  foreach ($DED as $c) $ded += $cell($c, $row);
    $ded += $cell($AX, $row);
    $net = $gross - $ded;

    $as = $cell('AS', $row);
    $diff = round($net - $as, 2);          // + = importer kelebihan, - = importer kurang
    if (abs($diff) < 1) continue;

    $beda++;
    $sumDiff += $diff;
    $diff > 0 ? $pos++ : $neg++;

    // subtotal Excel sendiri
    $afX = $cell('AF', $row);
    $arX = $cell('AR', $row);
    if (abs($gross - $afX) >= 1) $grossMismatch++;
    if (abs($ded - $arX) >= 1) $dedMismatch++;

    // kolom unmapped yang nilainya == |selisih| (kandidat komponen yang hilang)
    $explains = [];
    foreach ($unmappedCandidates as $c) {
        $v = $cell($c, $row);
        if ($v != 0) {
            $byUnmappedCol[$c] = ($byUnmappedCol[$c] ?? 0) + 1;
            if (abs(abs($v) - abs($diff)) < 1) $explains[] = "$c={$v} (== selisih)";
            else $explains[] = "$c={$v}";
        }
    }

    if (count($samples) < 25) {
        $samples[] = sprintf(
            "%-13s %-26s | net=%s AS=%s selisih=%+d | AF_xls=%s(Σearn=%s) AR_xls=%s(Σded=%s) | unmapped: %s",
            $e, mb_substr($b, 0, 26),
            number_format($net, 0), number_format($as, 0), $diff,
            number_format($afX, 0), number_format($gross, 0),
            number_format($arX, 0), number_format($ded, 0),
            $explains ? implode(', ', $explains) : '-'
        );
    }
}

echo str_repeat('=', 100) . "\n";
echo "Baris data (non-Mitra): $rows   |   net_beda: $beda\n";
echo "Arah selisih: importer KELEBIHAN $pos baris, importer KURANG $neg baris   |   total selisih Rp" . number_format($sumDiff, 0) . "\n";
echo "Subtotal Excel sendiri beda dari Σ komponen: AF(gross) $grossMismatch baris, AR(potongan) $dedMismatch baris\n\n";

echo "Kolom di luar mapping importer yang SERING ada isinya (kandidat komponen hilang):\n";
arsort($byUnmappedCol);
foreach ($byUnmappedCol as $c => $n) {
    echo sprintf("  %-4s : %d baris   (header: %s)\n", $c, $n, trim(preg_replace('/\s+/', ' ', (string) $s->getCell($c . '1')->getValue())));
}
if (! $byUnmappedCol) echo "  (tidak ada — berarti selisih bukan dari kolom komponen yang terlewat)\n";

echo "\nContoh baris net_beda (maks 25):\n";
foreach ($samples as $x) echo "  $x\n";
