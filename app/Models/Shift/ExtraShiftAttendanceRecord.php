<?php

namespace App\Models\Shift;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraShiftAttendanceRecord extends Model
{
    use HasUlids;

    protected $table = 'extra_shift_attendance_records';

    protected $fillable = [
        'extra_shift_assignment_id',
        'check_in_time',
        'check_out_time',
        'working_minutes',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'status',
        'out_of_window',
        'source',
        'recorded_by',
    ];

    protected $casts = [
        'out_of_window' => 'boolean',
    ];

    public function extraShiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ExtraShiftAssignment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Mapping bareng dari hasil ShiftScheduleCalculator - dipakai baik jalur otomatis
     * (SyncAccessLogsAttendance, bucket 'extra_shift' dari AccessLogScheduleReader) maupun
     * input manual (ExtraShiftPage::recordAttendance), biar mapping-nya cuma ada 1 tempat
     * (pola sama dengan AttendanceRecordRepository::saveCalculatedResult).
     */
    public static function saveCalculatedResult(array $r, string $extraShiftAssignmentId, string $source, ?string $recordedBy = null): self
    {
        return self::updateOrCreate(
            ['extra_shift_assignment_id' => $extraShiftAssignmentId],
            [
                'check_in_time' => $r['check_in'],
                'check_out_time' => $r['check_out'],
                'working_minutes' => $r['working_minutes'],
                'late_minutes' => $r['late_minutes'],
                'early_leave_minutes' => $r['early_leave_minutes'],
                'overtime_minutes' => $r['overtime_minutes'],
                'status' => $r['status'],
                'out_of_window' => $r['out_of_window'] ?? false,
                'source' => $source,
                'recorded_by' => $recordedBy,
            ]
        );
    }
}
