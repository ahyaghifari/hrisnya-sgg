<?php

namespace App\Helpers;

use Maatwebsite\Excel\Facades\Excel;

class ExcelValidationHelper
{
    public static function validateAttendanceFile(string $filePath): array
    {
        try {
            $collections = Excel::toCollection(collect(), $filePath);
            
            if ($collections->isEmpty()) {
                return [
                    'valid' => false,
                    'message' => 'File Excel kosong atau tidak dapat dibaca'
                ];
            }

            $rows = $collections->first();
            
            if ($rows->isEmpty()) {
                return [
                    'valid' => false,
                    'message' => 'Sheet pertama di file Excel kosong'
                ];
            }

            $firstRow = $rows->first()->toArray();
            $expectedColumns = ['nip', 'date', 'check_in_time', 'check_out_time', 'status'];
            
            // Check if first row contains headers
            $hasHeaders = false;
            foreach ($expectedColumns as $column) {
                if (in_array($column, $firstRow)) {
                    $hasHeaders = true;
                    break;
                }
            }

            // Validate minimum columns
            $columnCount = count($firstRow);
            if ($columnCount < 5) {
                return [
                    'valid' => false,
                    'message' => "File harus memiliki minimal 5 kolom (nip, date, check_in_time, check_out_time, status). Ditemukan {$columnCount} kolom."
                ];
            }

            // Check if we have actual data (excluding header)
            $dataRows = $hasHeaders ? $rows->skip(1) : $rows;
            if ($dataRows->isEmpty()) {
                return [
                    'valid' => false,
                    'message' => 'File tidak memiliki data attendance'
                ];
            }

            // Sample validation for first few data rows
            $sampleSize = min(3, $dataRows->count());
            $sampleRows = $dataRows->take($sampleSize);
            
            foreach ($sampleRows as $index => $row) {
                $rowArray = $row->toArray();
                $rowNumber = $index + ($hasHeaders ? 2 : 1);
                
                // Check nip
                if (empty($rowArray[0])) {
                    return [
                        'valid' => false,
                        'message' => "Employee code kosong pada baris {$rowNumber}"
                    ];
                }
                
                // Check date
                if (empty($rowArray[1])) {
                    return [
                        'valid' => false,
                        'message' => "Date kosong pada baris {$rowNumber}"
                    ];
                }
            }

            return [
                'valid' => true,
                'message' => 'File valid',
                'row_count' => $rows->count() - ($hasHeaders ? 1 : 0),
                'has_headers' => $hasHeaders
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Error membaca file: ' . $e->getMessage()
            ];
        }
    }
}