<?php

namespace App\Filament\Imports;

use App\Models\Attendance\AttendanceRecords;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttendanceImporter extends Importer
{
    protected static ?string $model = AttendanceRecords::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nip')
                ->label('NIP')
                ->requiredMapping()
                ->rules(['required']),
                
            ImportColumn::make('date')
                ->label('Tanggal')  
                ->requiredMapping()
                ->rules(['required']),
                
            ImportColumn::make('check_in_time')
                ->label('Jam Masuk')
                ->rules(['nullable']),
                
            ImportColumn::make('check_out_time')
                ->label('Jam Keluar')
                ->rules(['nullable']),
                
            ImportColumn::make('status')
                ->label('Status')
                ->rules(['nullable', 'string'])
        ];
    }

    public function resolveRecord(): ?Model
    {
        try {
            $nip = trim($this->data['nip']);
            $rawDate = $this->data['date'];
            $rawCheckIn = $this->data['check_in_time'] ?? null;
            $rawCheckOut = $this->data['check_out_time'] ?? null;
            $status = $this->data['status'] ?? 'present';

            // Find user by nip
            $user = User::where('nip', $nip)->first();
            
            if (!$user) {
                return null;
            }

            // Process date
            $date = $this->processDate($rawDate);
            if (!$date) {
                return null;
            }

            // Process times
            $checkInTime = $this->processTime($rawCheckIn);
            $checkOutTime = $this->processTime($rawCheckOut);

            // Calculate working minutes - stored as integer minutes
            $workingMinutes = null;
            if ($checkInTime && $checkOutTime) {
                try {
                    $checkIn = Carbon::createFromFormat('H:i:s', $checkInTime)->second(0);
                    $checkOut = Carbon::createFromFormat('H:i:s', $checkOutTime)->second(0);
                    
                    // Calculate difference properly (from checkin to checkout)
                    $diffInMinutes = $checkIn->diffInMinutes($checkOut, false);
                    
                    // If result is negative, it means checkout is next day
                    if ($diffInMinutes < 0) {
                        $diffInMinutes = (24 * 60) + $diffInMinutes; // Add 24 hours
                    }
                    
                    // Store as whole minutes
                    $workingMinutes = (int) round($diffInMinutes);
                } catch (\Exception $e) {
                    // Silent fail
                }
            }

            // Check if record already exists
            $existingRecord = AttendanceRecords::where('user_id', $user->id)
                ->where('date', $date)
                ->first();

            if ($existingRecord) {
                // Update existing record
                $existingRecord->update([
                    'nip' => $nip,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => $checkOutTime,
                    'working_minutes' => $workingMinutes,
                    'status' => $status,
                    'source' => 'import',
                ]);
                return $existingRecord;
            } else {
                // Create new record
                return AttendanceRecords::create([
                    'user_id' => $user->id,
                    'nip' => $nip,
                    'date' => $date,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => $checkOutTime,
                    'working_minutes' => $workingMinutes,
                    'status' => $status,
                    'source' => 'import',
                ]);
            }

        } catch (\Exception $e) {
            // Silent fail to prevent notification database issues
            return null;
        }
    }

    public function getJobQueue(): ?string
    {
        return null;
    }

    public function getJobConnection(): ?string
    {
        return null;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import absensi selesai. ' . number_format($import->successful_rows) . ' baris berhasil diimport.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal diimport.';
        }

        return $body;
    }

    protected function processDate($dateValue): ?string
    {
        if (empty($dateValue)) {
            return null;
        }

        // Handle Excel serial date
        if (is_numeric($dateValue)) {
            try {
                $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
                return $dateObj->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        // Handle string dates
        try {
            return Carbon::parse($dateValue)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function processTime($timeValue): ?string
    {
        if (empty($timeValue)) {
            return null;
        }

        // Handle Excel serial time (decimal values like 0.25 for 6:00 AM)
        if (is_numeric($timeValue)) {
            try {
                $timeObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($timeValue);
                return $timeObj->format('H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }

        // Handle string times
        try {
            return Carbon::parse($timeValue)->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
