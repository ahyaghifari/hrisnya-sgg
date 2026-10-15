<?php

namespace App\Models\Attendance;

use App\Models\Shift\MasterShift;
use App\Models\Shift\ShiftScheduleAssignments;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecords extends Model
{
    use HasUlids;

    protected $table = 'attendance_records';

    protected $fillable = [
        'user_id',
        'nip',
        'date',
        'schedule_id',
        'shift_id',
        'check_in_time',
        'check_out_time',
        'working_minutes',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'permission_minutes',
        'status',
        'source',
        'source_request_id',
        'is_auto_calculated',
        'work_day_weight',
        'meets_target',
        'out_of_window',
        'scan_sessions',
    ];

    protected $casts = [
        'is_auto_calculated' => 'boolean',
        'work_day_weight'    => 'decimal:1',
        'meets_target'       => 'boolean',
        'out_of_window'      => 'boolean',
        'scan_sessions'      => 'array',
    ];

    /** Merit Point otomatis "Terlambat": satu record telat = satu transaksi (idempotent per record);
     * kalau late_minutes dikoreksi jadi 0 transaksinya dicabut lagi. */
    protected static function booted(): void
    {
        static::saved(function (self $record) {
            if (! $record->wasRecentlyCreated && ! $record->wasChanged('late_minutes')) {
                return;
            }

            $merit = app(\App\Services\MeritPoint\MeritPointService::class);

            if (($record->late_minutes ?? 0) > 0) {
                $merit->applyEvent(
                    \App\Enum\MeritPoint\MeritPointEvent::LATE,
                    $record->user_id,
                    $record->user?->company_id,
                    'attendance_record',
                    $record->id,
                    "Terlambat {$record->late_minutes} menit ({$record->date})",
                );
            } else {
                $merit->revokeEvent($record->user_id, 'attendance_record', $record->id);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function shift()
    {
        return $this->belongsTo(MasterShift::class, 'shift_id');
    }

    public function schedule()
    {
        return $this->belongsTo(ShiftScheduleAssignments::class, 'schedule_id');
    }
}
