<?php

namespace App\Models\LMS;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Izin peserta tidak hadir di satu pertemuan pelatihan. Persetujuan lewat ApprovalFlowService. */
class LmsCourseAbsence extends Model
{
    use HasUlids, BelongsToCompany;

    protected $table = 'lms_course_absences';

    protected $fillable = [
        'company_id', 'course_id', 'meeting_id', 'user_id', 'reason', 'status',
        'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'cancelled_by', 'cancelled_at',
    ];

    protected $casts = ['approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id');
    }

    public function meeting()
    {
        return $this->belongsTo(LmsMeetings::class, 'meeting_id');
    }

    public function approvalHistories()
    {
        return $this->hasMany(\App\Models\ApprovalManagement\ApprovalHistories::class, 'requestable_id', 'id')
            ->where('requestable_type', ApprovalModuleEnum::TRAINING_ABSENCE->value);
    }

    /** Peserta boleh batal: masih diproses, atau sudah disetujui tapi pertemuannya belum lewat (sampai hari H). */
    public function canBeCancelled(): bool
    {
        if ($this->status === 'in_review') {
            return true;
        }

        $date = $this->meeting?->start_date ?? $this->meeting?->meeting_date;

        return $this->status === 'approved'
            && $date
            && \Carbon\Carbon::parse($date)->toDateString() >= now()->toDateString();
    }

    public function approvalSnapshot()
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::TRAINING_ABSENCE->value);
    }
}
