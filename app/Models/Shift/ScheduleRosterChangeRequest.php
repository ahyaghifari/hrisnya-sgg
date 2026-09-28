<?php

namespace App\Models\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan perubahan roster harian dari Kepala Ruangan yang cuma punya
 * Update:ShiftScheduleAssignments (bukan Update:Schedule) - dibuat oleh
 * ScheduleRosterChangeService::submitForApproval() dari sel-sel yang lolos gerbang
 * ScheduleShiftRosterTab::saveRoster(), dieksekusi ke schedule_shift_assignments (lihat
 * RosterBatchWriter) begitu disetujui lewat ApprovalFlowService::advanceStep().
 */
class ScheduleRosterChangeRequest extends Model
{
    use HasUlids;
    use BelongsToCompany;

    protected $table = 'schedule_roster_change_requests';

    protected $fillable = [
        'company_id',
        'schedule_id',
        'period',
        'payload',
        'requested_by',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'notes',
    ];

    protected $casts = [
        'payload' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Alias requester() - App\Services\Approval\ApprovalTimelineService::build() generik
     * lintas modul, cuma baca relasi bernama user(). */
    public function user()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvalSnapshot()
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::SCHEDULE_ROSTER_CHANGE->value);
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id', 'id')
            ->where('requestable_type', ApprovalModuleEnum::SCHEDULE_ROSTER_CHANGE->value);
    }
}
