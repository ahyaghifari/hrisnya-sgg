<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = $argv[1];
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(false); // keep formulas
$spreadsheet = $reader->load($path);

foreach ($spreadsheet->getAllSheets() as $sheet) {
    $title = $sheet->getTitle();
    echo "\n================ SHEET: {$title} ================\n";
    $highestRow = $sheet->getHighestDataRow();
    $highestCol = $sheet->getHighestDataColumn();
    echo "dims: {$highestCol}{$highestRow}\n";

    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    for ($row = 1; $row <= $highestRow; $row++) {
        $cells = [];
        $hasContent = false;
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $cell = $sheet->getCell($colLetter . $row);
            $val = $cell->getValue();
            if ($val === null || $val === '') {
                continue;
            }
            $hasContent = true;
            if (is_string($val) && strlen($val) > 0 && $val[0] === '=') {
                $calc = '';
                try { $calc = $cell->getCalculatedValue(); } catch (\Throwable $e) { $calc = '[ERR]'; }
                $cells[] = "{$colLetter}{$row}: {$val}  => " . (is_scalar($calc) ? $calc : json_encode($calc));
            } else {
                $cells[] = "{$colLetter}{$row}: " . (is_scalar($val) ? $val : json_encode($val));
            }
        }
        if ($hasContent) {
            echo "R{$row} | " . implode("  ||  ", $cells) . "\n";
        }
    }
}
