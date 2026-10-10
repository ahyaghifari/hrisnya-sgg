<?php

namespace App\Repositories\Attendance;

use App\Enum\AttendanceStatus;
use App\Models\Attendance\AttendanceRecords;
use App\Models\User;

class AttendanceRecordRepository
{
    public function updateOrCreate(array $data): AttendanceRecords
    {
        // Try to find user by nip (fallback when user_id is not yet resolved)
        $user = isset($data['nip']) ? User::whereNip((string) $data['nip'])->first() : null;
        
        // Prepare data for insert/update
        $recordData = [
            'user_id'             => $data['user_id'] ?? $user?->id,
            'schedule_id'         => $data['schedule_id'] ?? null,
            'shift_id'            => $data['shift_id'] ?? null,
            'check_in_time'       => $data['check_in_time'] ?? null,
            'check_out_time'      => $data['check_out_time'] ?? null,
            'working_minutes'     => $data['working_minutes'] ?? null,
            'late_minutes'        => $data['late_minutes'] ?? null,
            'early_leave_minutes' => $data['early_leave_minutes'] ?? null,
            'overtime_minutes'    => $data['overtime_minutes'] ?? null,
            'permission_minutes'  => $data['permission_minutes'] ?? null,
            'status'              => $data['status'] ?? 'present',
            'source'              => $data['source'] ?? 'import',
            'is_auto_calculated'  => $data['is_auto_calculated'] ?? false,
            'work_day_weight'     => $data['work_day_weight'] ?? null,
            'meets_target'        => $data['meets_target'] ?? null,
            'out_of_window'       => $data['out_of_window'] ?? false,
        ];

        // Sengaja TIDAK ada log per-simpan di sini: dipanggil tiap scan oleh cron presensi dan
        // memenuhi laravel.log. Jejak presensi ada di channel `attendance_sync`
        // (SyncAccessLogsAttendance / SweepMissedAccessLogs).
        return AttendanceRecords::updateOrCreate(
            [
                'nip'  => $data['nip'],
                'date' => $data['date'],
            ], 
            $recordData
        );
    }

    /**
     * Simpan hasil satu entri dari FixedScheduleCalculator/FlexibleScheduleCalculator/ShiftScheduleCalculator (dipakai
     * App\Console\Commands\ProcessAccessLogsSchedule dan App\Console\Commands\SyncAccessLogsAttendance,
     * biar mapping-nya cuma ada di satu tempat).
     */
    /** Baris yang statusnya sudah Cuti/Sakit/Izin (disetujui - lihat
     * AttendaceReqService::markAttendanceRecordAbsence()) TIDAK BOLEH ditimpa sync device (scan
     * nyasar/keliru hari itu tetap mungkin ada meski karyawannya lagi cuti) - dikembalikan apa
     * adanya tanpa disentuh sama sekali, dipanggil dari Rekap Presensi (runSync/resyncUserRange)
     * maupun command terjadwal (SyncAccessLogsAttendance/ProcessAccessLogsSchedule/
     * SweepMissedAccessLogs) yang sama-sama lewat method ini. Instruksi user 2026-09-27. */
    public function saveCalculatedResult(array $r, string $source): AttendanceRecords
    {
        $existing = $this->findByEmployeeCodeAndDate($r['user']->nip, $r['date']);

        $lockedStatuses = [AttendanceStatus::LEAVE->value, AttendanceStatus::SICK->value, AttendanceStatus::PERMIT->value];

        if ($existing && in_array($existing->status, $lockedStatuses, true)) {
            return $existing;
        }

        return $this->updateOrCreate([
            'nip'                 => $r['user']->nip,
            'date'                => $r['date'],
            'user_id'             => $r['user']->id,
            'check_in_time'       => $r['check_in'],
            'check_out_time'      => $r['check_out'],
            'working_minutes'     => $r['working_minutes'],
            'late_minutes'        => $r['late_minutes'],
            'early_leave_minutes' => $r['early_leave_minutes'],
            'overtime_minutes'    => $r['overtime_minutes'],
            'work_day_weight'     => $r['work_day_weight'],
            // meets_target cuma dihasilkan FlexibleScheduleCalculator - Fixed tidak punya
            // key ini sama sekali di array hasilnya (konsepnya memang tidak berlaku untuk Fixed).
            'meets_target'        => $r['meets_target'] ?? null,
            'out_of_window'       => $r['out_of_window'] ?? false,
            'status'              => $r['status'],
            'source'              => $source,
            'is_auto_calculated'  => true,
        ]);
    }

    public function findByEmployeeCodeAndDate(string $employeeCode, string $date): ?AttendanceRecords
    {
        return AttendanceRecords::where('nip', $employeeCode)
            ->where('date', $date)
            ->first();
    }

    public function getByEmployeeCode(string $employeeCode, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return AttendanceRecords::where('nip', $employeeCode)
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getByDateRange(string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return AttendanceRecords::whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->orderBy('nip')
            ->get();
    }
}
