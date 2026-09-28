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
 * "Wrapper" pengajuan perubahan roster harian dari Kepala Ruangan yang cuma punya
 * Update:ShiftScheduleAssignments (bukan Update:Schedule) - dibuat/dipakai ulang oleh
 * ScheduleRosterChangeService::submitForApproval() SELAMA masih in_review (karu boleh terus edit,
 * tidak bikin request baru tiap Simpan). Diff SEBENARNYA hidup di baris schedule_shift_assignments
 * sendiri (kolom draft_request_id/draft_shift_type_id/draft_off_day, lihat draftAssignments()) -
 * tabel ini sejak 2026-09-28 murni status + siapa/kapan, bukan penyimpan JSON payload lagi.
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
        'requested_by',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /** Baris roster yang lagi menunggu keputusan atas request ini - lihat docblock class. */
    public function draftAssignments()
    {
        return $this->hasMany(ScheduleShiftAssignment::class, 'draft_request_id');
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
