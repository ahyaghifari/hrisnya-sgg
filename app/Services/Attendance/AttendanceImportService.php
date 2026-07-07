<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceUploads;
use App\Repositories\Attendance\AttendanceImportRepository;
use App\Repositories\Attendance\AttendanceRecordRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceImportService
{
    public function __construct(
        protected AttendanceRecordRepository $attendanceRecordRepository,
        protected AttendanceImportRepository $importRepository,
    ) {}
        public function syncRequestsForRecords(
        string $fromDate,
        string $toDate,
        ?string $userId = null,
        ?string $companyId = null
    ): array {
        Log::info("Starting request sync: fromDate={$fromDate}, toDate={$toDate}, userId={$userId}, companyId={$companyId}");

        $processed = 0;
        $errors    = 0;

        try {
            DB::beginTransaction();

            $query = AttendanceRecords::whereBetween('date', [$fromDate, $toDate])
                ->whereNotNull('check_in_time')
                ->with('user');

            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($companyId) {
                $query->whereHas('user', fn ($q) => $q->where('company_id', $companyId));
            }

            $records = $query->get();

            foreach ($records as $record) {
                try {
                    if (!$record->user_id) {
                        continue;
                    }

                    $date = $record->date instanceof Carbon
                        ? $record->date->toDateString()
                        : (string) $record->date;

                    $assignment = $this->importRepository->findShiftAssignmentForDate($record->user_id, $date);
                    $shift      = $assignment?->shift;

                    $requestsData = $this->resolveAttendanceRequests($record->user_id, $date);

                    // Recompute all metrics
                    $metrics = $this->computeMetricsFromCheckTimes(
                        $record->nip ?? '',
                        $date,
                        $record->check_in_time,
                        $record->check_out_time,
                        $shift,
                        $requestsData
                    );

                    $record->update(array_merge($metrics, [
                        'schedule_id' => $assignment?->id ?? $record->schedule_id,
                        'shift_id'    => $shift?->id     ?? $record->shift_id,
                    ]));

                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::warning("Sync error for record ID={$record->id}: " . $e->getMessage());
                }
            }

            $leaveRequests = $this->importRepository->findApprovedLeaveRequestsInRange(
                $fromDate, $toDate, $userId, $companyId
            );

            $syncFrom = Carbon::parse($fromDate);
            $syncTo   = Carbon::parse($toDate);

            foreach ($leaveRequests as $leaveRequest) {
                $leaveUserId = $leaveRequest->user_id;
                $leaveUser   = $leaveRequest->user;

                foreach ($leaveRequest->leaveDetails as $detail) {
                    $detailStart = Carbon::parse($detail->start_date);
                    // null end_date → single-day leave
                    $detailEnd = $detail->end_date
                        ? Carbon::parse($detail->end_date)
                        : $detailStart->copy();

                    // Clamp to the sync window
                    $rangeStart = $detailStart->lt($syncFrom) ? $syncFrom->copy() : $detailStart->copy();
                    $rangeEnd   = $detailEnd->gt($syncTo)     ? $syncTo->copy()   : $detailEnd->copy();

                    $current = $rangeStart->copy();
                    while ($current->lte($rangeEnd)) {
                        $dateStr = $current->toDateString();

                        try {
                            $assignment = $this->importRepository->findShiftAssignmentForDate($leaveUserId, $dateStr);

                            $this->attendanceRecordRepository->updateOrCreate([
                                'nip'  => $leaveUser->nip,
                                'date' => $dateStr,
                            ] + [
                                'user_id' => $leaveUserId,
                                'schedule_id' => $assignment?->id,
                                'shift_id'    => $assignment?->shift_id,
                                'check_in_time' => null,
                                'check_out_time' => null,
                                'working_minutes' => null,
                                'late_minutes' => null,
                                'early_leave_minutes' => null,
                                'overtime_minutes' => null,
                                'permission_minutes' => null,
                                'status' => 'leave',
                            ]);

                            $processed++;
                        } catch (\Exception $e) {
                            $errors++;
                            Log::warning("Error applying leave for user_id={$leaveUserId} on {$dateStr}: " . $e->getMessage());
                        }

                        $current->addDay();
                    }
                }
            }

            DB::commit();

            $message = "Sync selesai. {$processed} record berhasil disinkronkan"
                . ($errors > 0 ? ", {$errors} error" : "");

            Log::info("Request sync completed: processed={$processed}, errors={$errors}");

            return [
                'success'   => true,
                'processed' => $processed,
                'errors'    => $errors,
                'message'   => $message,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Request sync failed: " . $e->getMessage());

            return [
                'success'   => false,
                'processed' => 0,
                'errors'    => 0,
                'message'   => "Sync gagal: " . $e->getMessage(),
            ];
        }
    }

    public function import(AttendanceUploads $upload): array
    {
        Log::info("Starting import for upload ID: {$upload->id}");
        
        try {
            // Check file exists using Storage
            if (!Storage::disk('local')->exists($upload->file_path)) {
                throw new \Exception("File tidak ditemukan: {$upload->file_path}");
            }
            
            $filePath = Storage::disk('local')->path($upload->file_path);

            // Read Excel file
            $collections = Excel::toCollection(collect(), $filePath);
            if ($collections->isEmpty()) {
                throw new \Exception("File Excel kosong atau tidak dapat dibaca");
            }

            $rows = $collections->first(); // Get first sheet
            $processedCount = 0;
            $errorCount = 0;
            $errors = [];
            $totalRows = $rows->count();

            // Detect header row and get column mapping
            $headerMapping = null;
            if ($totalRows > 0) {
                $headerRow = $rows->first()->toArray();
                Log::info("Header row detected: " . json_encode($headerRow));
                $headerMapping = $this->detectHeaderMapping($headerRow);
                
                if ($headerMapping) {
                    Log::info("Header mapping: " . json_encode($headerMapping));
                    $rows = $rows->skip(1);
                    $totalRows--;
                }
            }

            if (!$headerMapping) {
                throw new \Exception("Header tidak dapat dideteksi. Pastikan file Excel memiliki header yang sesuai.");
            }

            DB::beginTransaction();
            
            // Group rows by NIP and Date
            $groupedData = [];
            
            foreach ($rows as $index => $row) {
                try {
                    $rowNumber = $index + 2; // +2 karena index mulai 0 dan ada header
                    
                    // Log first 3 rows for debugging
                    if ($index < 3) {
                        Log::info("Row {$rowNumber} raw data: " . json_encode($row->toArray()));
                    }
                    
                    $rowData = $this->extractRowData($row->toArray(), $headerMapping, $rowNumber);
                    
                    if ($rowData) {
                        $key = $rowData['nip'] . '_' . $rowData['date'];
                        
                        if (!isset($groupedData[$key])) {
                            $groupedData[$key] = [
                                'nip' => $rowData['nip'],
                                'date' => $rowData['date'],
                                'times' => [],
                            ];
                        }
                        
                        // Add time to the group
                        if ($rowData['time']) {
                            $groupedData[$key]['times'][] = $rowData['time'];
                        }
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'row' => $index + 2,
                        'data' => $row->toArray(),
                        'error' => $e->getMessage()
                    ];
                    Log::warning("Error processing row " . ($index + 2) . ": " . $e->getMessage());
                }
            }

            // Process grouped data
            foreach ($groupedData as $key => $group) {
                try {
                    $shiftData    = $this->resolveShiftForUser($group['nip'], $group['date']);
                    $requestsData = $shiftData ? $this->resolveAttendanceRequests($shiftData['user']->id, $group['date']) : [];
                    $data         = $this->processGroupedData($group, $shiftData, $requestsData);
                    
                    if ($data) {
                        Log::info(
                            "Saving attendance record: NIP={$data['nip']}, Date={$data['date']}, " .
                            "Check-in={$data['check_in_time']}, Check-out={$data['check_out_time']}, " .
                            "Minutes={$data['working_minutes']}, Status={$data['status']}, " .
                            "Late={$data['late_minutes']}min, EarlyLeave={$data['early_leave_minutes']}min, " .
                            "Overtime={$data['overtime_minutes']}min, Permission={$data['permission_minutes']}min"
                        );
                        $this->attendanceRecordRepository->updateOrCreate($data);
                        $processedCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'key' => $key,
                        'data' => $group,
                        'error' => $e->getMessage()
                    ];
                    Log::warning("Error processing grouped data {$key}: " . $e->getMessage());
                }
            }

            // Update upload record
            $upload->update([
                'status' => $errorCount === 0 ? 'processed' : 'processed_with_errors',
                'record_count' => $totalRows,
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'error_log' => $errorCount > 0 ? $errors : null,
                'processed_at' => now(),
            ]);

            DB::commit();
            
            Log::info("Import completed for upload ID: {$upload->id}. Processed: {$processedCount}, Errors: {$errorCount}");
            
            return [
                'success' => true,
                'processed' => $processedCount,
                'errors' => $errorCount,
                'total' => count($groupedData),
                'message' => "Import selesai. {$processedCount} record berhasil diproses" . ($errorCount > 0 ? ", {$errorCount} record error" : "")
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            
            $upload->update([
                'status' => 'failed',
                'error_log' => [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ],
                'processed_at' => now(),
            ]);
            
            Log::error("Import failed for upload ID: {$upload->id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'processed' => 0,
                'errors' => 0,
                'total' => 0,
                'message' => $e->getMessage()
            ];
        }
    }

    private function normalizeHeaderName(string $header): string
    {
        return strtolower(preg_replace('/[\s\-_]+/', '_', trim($header)));
    }

    private function detectHeaderMapping(array $row): ?array
    {
        $headerAliases = [
            'tanggal_scan' => ['tanggal_scan', 'tanggalscan', 'tgl_scan', 'tglscan'],
            'tanggal' => ['tanggal', 'tgl', 'date'],
            'jam' => ['jam', 'waktu', 'time', 'pukul'],
            'pin' => ['pin', 'pin_code', 'pincode'],
            'nip' => ['nip', 'nik', 'employee_code', 'kode_karyawan', 'kodekaryawan'],
            'nama' => ['nama', 'name', 'nama_karyawan', 'namakaryawan', 'employee_name'],
            'jabatan' => ['jabatan', 'position', 'posisi'],
            'departemen' => ['departemen', 'department', 'dept'],
            'kantor' => ['kantor', 'office', 'lokasi', 'location'],
            'verifikasi' => ['verifikasi', 'verification', 'verify'],
            'io' => ['i/o', 'io', 'i_o', 'in_out', 'inout'],
            'workcode' => ['workcode', 'work_code', 'kode_kerja', 'kodekerja'],
            'sn' => ['sn', 'serial_number', 'serialnumber'],
            'mesin' => ['mesin', 'machine', 'device', 'perangkat'],
        ];

        $mapping = [];
        $normalizedRow = array_map(fn($h) => $this->normalizeHeaderName($h), $row);

        foreach ($normalizedRow as $index => $normalizedHeader) {
            foreach ($headerAliases as $standardName => $aliases) {
                if (in_array($normalizedHeader, $aliases)) {
                    $mapping[$standardName] = $index;
                    break;
                }
            }
        }

        // Check if we have the minimum required columns
        $requiredColumns = ['nip', 'tanggal', 'jam'];
        foreach ($requiredColumns as $required) {
            if (!isset($mapping[$required])) {
                return null;
            }
        }

        return $mapping;
    }

    private function extractRowData(array $row, array $mapping, int $rowNumber): ?array
    {
        // Extract NIP
        $nip = isset($mapping['nip']) ? ($row[$mapping['nip']] ?? null) : null;
        
        // Convert numeric NIP to string and trim
        if (!empty($nip)) {
            $nip = trim((string) $nip);
        }
        
        if (empty($nip)) {
            throw new \Exception("NIP tidak boleh kosong pada baris {$rowNumber}");
        }

        // Extract date - prioritas: tanggal > tanggal_scan
        $dateValue = null;
        if (isset($mapping['tanggal']) && !empty($row[$mapping['tanggal']])) {
            $dateValue = $row[$mapping['tanggal']];
        } elseif (isset($mapping['tanggal_scan']) && !empty($row[$mapping['tanggal_scan']])) {
            $dateValue = $row[$mapping['tanggal_scan']];
        }

        if (empty($dateValue)) {
            throw new \Exception("Tanggal tidak boleh kosong pada baris {$rowNumber}");
        }

        // Extract time
        $timeValue = isset($mapping['jam']) ? ($row[$mapping['jam']] ?? null) : null;
        if (empty($timeValue)) {
            throw new \Exception("Jam tidak boleh kosong pada baris {$rowNumber}");
        }

        // Process date and time
        $date = $this->processDate($dateValue, $rowNumber);
        $time = $this->processTime($timeValue);

        if (!$time) {
            throw new \Exception("Waktu tidak valid pada baris {$rowNumber}");
        }

        return [
            'nip' => $nip,
            'date' => $date,
            'time' => $time,
        ];
    }

    private function resolveShiftForUser(string $nip, string $date): ?array
    {
        $user = $this->importRepository->findUserByNip($nip);

        if (!$user) {
            Log::warning("User not found for NIP={$nip}, skipping record");
            return null;
        }

        $assignment = $this->importRepository->findShiftAssignmentForDate($user->id, $date);

        Log::info(
            "Shift resolved for NIP={$nip} on {$date}: " .
            ($assignment?->shift?->name ?? 'no shift')
        );

        return [
            'user'     => $user,
            'schedule' => $assignment,
            'shift'    => $assignment?->shift,
        ];
    }

    private function resolveAttendanceRequests(string $userId, string $date): array
    {
        $overtimeRequests   = $this->importRepository->findApprovedOvertimeRequestsForDate($userId, $date);
        $leaveRequests      = $this->importRepository->findApprovedLeaveRequestsForDate($userId, $date);
        $permissionRequests = $this->importRepository->findApprovedPermissionRequestsForDate($userId, $date);

        Log::info(
            "Requests resolved for user_id={$userId} on {$date}: " .
            "overtime={$overtimeRequests->count()}, " .
            "leave={$leaveRequests->count()}, " .
            "permission={$permissionRequests->count()}"
        );

        return [
            'overtime'   => $overtimeRequests,
            'leave'      => $leaveRequests,
            'permission' => $permissionRequests,
        ];
    }

    private function calculateOvertimeMinutes(
        \Illuminate\Support\Collection $overtimeRequests,
        ?string $checkOutTime,
        ?string $shiftEndTime
    ): int {
        if ($overtimeRequests->isEmpty()) {
            return 0;
        }

        $totalMinutes = 0;

        foreach ($overtimeRequests as $request) {
            foreach ($request->overtimeDetails as $detail) {
                try {
                    $totalMinutes += (int) round((float) $detail->total_hours * 60);
                } catch (\Exception $e) {
                    Log::warning("Error calculating overtime detail: " . $e->getMessage());
                }
            }
        }

        return $totalMinutes;
    }

    /**
     * Calculate total permission minutes from approved permission request details on this date.
     */
    private function calculatePermissionMinutes(\Illuminate\Support\Collection $permissionRequests): int
    {
        $total = 0;

        foreach ($permissionRequests as $request) {
            foreach ($request->permissionDetails as $detail) {
                try {
                    $start = Carbon::createFromFormat('H:i:s', $detail->start_time)->second(0);
                    $end   = Carbon::createFromFormat('H:i:s', $detail->end_time)->second(0);
                    if ($end->lt($start)) {
                        $end->addDay();
                    }
                    $total += (int) $start->diffInMinutes($end);
                } catch (\Exception $e) {
                    Log::warning("Error calculating permission detail: " . $e->getMessage());
                }
            }
        }

        return $total;
    }

    private function computeMetricsFromCheckTimes(
        string $nip,
        string $date,
        ?string $checkInTime,
        ?string $checkOutTime,
        mixed $shift,
        array $requestsData
    ): array {
        $overtimeRequests   = $requestsData['overtime']   ?? collect();
        $leaveRequests      = $requestsData['leave']      ?? collect();
        $permissionRequests = $requestsData['permission'] ?? collect();

        $workingMinutes    = null;
        $lateMinutes       = null;
        $earlyLeaveMinutes = null;
        $overtimeMinutes   = null;
        $permissionMinutes = null;
        $status            = 'present';

        // --- Shift-based metrics & working hours ---
        if ($shift) {
            try {
                $shiftStart  = Carbon::createFromFormat('H:i:s', $shift->start_time)->second(0);
                $shiftEnd    = Carbon::createFromFormat('H:i:s', $shift->end_time)->second(0);
                $isOvernight = (bool) ($shift->is_overnight ?? false);

                if ($isOvernight && $shiftEnd->lte($shiftStart)) {
                    $shiftEnd->addDay();
                }

                $isLate       = false;
                $isEarlyLeave = false;

                // Determine late
                if ($checkInTime) {
                    $ci = Carbon::createFromFormat('H:i:s', $checkInTime)->second(0);
                    if ($ci->gt($shiftStart)) {
                        $isLate      = true;
                        $lateMinutes = (int) $shiftStart->diffInMinutes($ci);
                        $status      = 'late';
                    }
                }

                // Determine early leave
                if ($checkOutTime) {
                    $co = Carbon::createFromFormat('H:i:s', $checkOutTime)->second(0);
                    if ($isOvernight && $co->lt(Carbon::createFromFormat('H:i:s', $shift->start_time)->second(0))) {
                        $co->addDay();
                    }
                    if ($co->lt($shiftEnd)) {
                        $isEarlyLeave      = true;
                        $earlyLeaveMinutes = (int) $co->diffInMinutes($shiftEnd);
                    }
                }

                // Working hours based on late/early-leave combination
                if ($checkInTime && $checkOutTime) {
                    $ci = Carbon::createFromFormat('H:i:s', $checkInTime)->second(0);
                    $co = Carbon::createFromFormat('H:i:s', $checkOutTime)->second(0);
                    if ($isOvernight && $co->lt(Carbon::createFromFormat('H:i:s', $shift->start_time)->second(0))) {
                        $co->addDay();
                    }

                    if (!$isLate && !$isEarlyLeave) {
                        // On time in & out → use full shift duration
                        $whStart = $shiftStart->copy();
                        $whEnd   = $shiftEnd->copy();
                    } elseif ($isLate && !$isEarlyLeave) {
                        // Late in, on time out → employee check-in to shift end
                        $whStart = $ci->copy();
                        $whEnd   = $shiftEnd->copy();
                    } elseif (!$isLate && $isEarlyLeave) {
                        // On time in, early out → shift start to employee check-out
                        $whStart = $shiftStart->copy();
                        $whEnd   = $co->copy();
                    } else {
                        // Late in & early out → employee check-in to employee check-out
                        $whStart = $ci->copy();
                        $whEnd   = $co->copy();
                    }

                    if ($whEnd->lt($whStart)) {
                        $whEnd->addDay();
                    }

                    $workingMinutes = (int) round($whStart->diffInMinutes($whEnd));
                }
            } catch (\Exception $e) {
                Log::warning("Shift metrics calc error for NIP={$nip} on {$date}: " . $e->getMessage());
            }
        } elseif ($checkInTime && $checkOutTime) {
            // No shift data — fall back to raw check-in/check-out
            try {
                $ci = Carbon::createFromFormat('H:i:s', $checkInTime)->second(0);
                $co = Carbon::createFromFormat('H:i:s', $checkOutTime)->second(0);
                if ($co->lt($ci)) {
                    $co->addDay();
                }
                $workingMinutes = (int) round($ci->diffInMinutes($co));
            } catch (\Exception $e) {
                Log::warning("Working minutes calc error for NIP={$nip} on {$date}: " . $e->getMessage());
            }
        }

        // --- Overtime from approved requests (overrides shift-based rough calc) ---
        if ($overtimeRequests->isNotEmpty()) {
            try {
                $calculatedOT = $this->calculateOvertimeMinutes($overtimeRequests, $checkOutTime, $shift?->end_time);

                if ($calculatedOT > 0) {
                    $overtimeMinutes   = $calculatedOT;
                    $earlyLeaveMinutes = null;
                    if ($status !== 'late') {
                        $status = 'overtime';
                    }
                }

                Log::info("Overtime from request for NIP={$nip} on {$date}: {$calculatedOT} min");
            } catch (\Exception $e) {
                Log::warning("Overtime request calc error for NIP={$nip} on {$date}: " . $e->getMessage());
            }
        }

        // --- Permission minutes ---
        if ($permissionRequests->isNotEmpty()) {
            try {
                $permissionMinutes = $this->calculatePermissionMinutes($permissionRequests) ?: null;
                if ($permissionMinutes) {
                    if ($status === 'present') {
                        $status = 'permission';
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Permission calc error for NIP={$nip} on {$date}: " . $e->getMessage());
            }
        }

        // --- Leave: highest-priority status override ---
        if ($leaveRequests->isNotEmpty()) {
            $status            = 'leave';
            $lateMinutes       = null;
            $earlyLeaveMinutes = null;
            Log::info("Leave applied for NIP={$nip} on {$date}");
        }

        return [
            'working_minutes'     => $workingMinutes,
            'late_minutes'        => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes'    => $overtimeMinutes,
            'permission_minutes'  => $permissionMinutes,
            'status'              => $status,
        ];
    }

    private function processGroupedData(array $group, ?array $shiftData = null, array $requestsData = []): ?array
    {
        if (empty($group['times'])) {
            return null;
        }

        $times = $group['times'];
        sort($times);

        $checkInTime  = $times[0];
        $checkOutTime = count($times) > 1 ? end($times) : null;

        $user     = $shiftData['user']     ?? null;
        $shift    = $shiftData['shift']    ?? null;
        $schedule = $shiftData['schedule'] ?? null;

        $metrics = $this->computeMetricsFromCheckTimes(
            $group['nip'],
            $group['date'],
            $checkInTime,
            $checkOutTime,
            $shift,
            $requestsData
        );

        return array_merge([
            'nip'          => $group['nip'],
            'date'         => $group['date'],
            'user_id'      => $user?->id,
            'schedule_id'  => $schedule?->id,
            'shift_id'     => $shift?->id,
            'check_in_time'  => $checkInTime,
            'check_out_time' => $checkOutTime,
        ], $metrics);
    }

    private function processDate($dateValue, int $rowNumber): string
    {
        if (empty($dateValue)) {
            throw new \Exception("Date kosong pada baris {$rowNumber}");
        }

        // Handle Excel serial date
        if (is_numeric($dateValue) && $dateValue > 0) {
            try {
                // Excel date serial starts from 1900-01-01
                $dateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
                return $dateTime->format('Y-m-d');
            } catch (\Exception $e) {
                throw new \Exception("Format tanggal tidak valid pada baris {$rowNumber}: {$dateValue}");
            }
        }

        // Handle string dates - support various formats
        try {
            $parsedDate = Carbon::parse($dateValue);
            return $parsedDate->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \Exception("Format tanggal tidak valid pada baris {$rowNumber}: {$dateValue}");
        }
    }

    private function processTime($timeValue): ?string
    {
        if (empty($timeValue)) {
            return null;
        }

        // Handle Excel serial time (decimal number between 0 and 1 representing fraction of a day)
        if (is_numeric($timeValue)) {
            try {
                // If value is less than 1, it's a time fraction (e.g., 0.3125 = 07:30)
                if ($timeValue < 1) {
                    $totalSeconds = $timeValue * 86400; // 86400 seconds in a day
                    $hours = floor($totalSeconds / 3600);
                    $minutes = floor(($totalSeconds % 3600) / 60);
                    $seconds = $totalSeconds % 60;
                    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                }
                
                // If value is greater than 1, treat as Excel datetime serial
                $dateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($timeValue);
                return $dateTime->format('H:i:s');
            } catch (\Exception $e) {
                Log::warning("Error converting time value {$timeValue}: " . $e->getMessage());
                return null;
            }
        }

        // Handle string times
        try {
            $parsed = Carbon::parse($timeValue);
            return $parsed->format('H:i:s');
        } catch (\Exception $e) {
            Log::warning("Error parsing time string {$timeValue}: " . $e->getMessage());
            return null;
        }
    }
}
