<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class McuInvitee extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['mcu_schedule_id', 'job_application_id', 'status_mcu', 'catatan_mcu'];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(McuSchedule::class, 'mcu_schedule_id');
    }

    public function nurseAssessment(): HasOne
    {
        return $this->hasOne(McuNurseAssessment::class);
    }

    public function labResult(): HasOne
    {
        return $this->hasOne(McuLabResult::class);
    }

    public function radiologyResult(): HasOne
    {
        return $this->hasOne(McuRadiologyResult::class);
    }

    public function doctorExamination(): HasOne
    {
        return $this->hasOne(McuDoctorExamination::class);
    }

    public function doctorExamDone(): bool
    {
        return $this->doctorExamination()->exists();
    }

    public function hasAnyMcuResult(): bool
    {
        return $this->nurseAssessment()->exists()
            || $this->labResult()->exists()
            || $this->radiologyResult()->exists()
            || $this->doctorExamination()->exists();
    }
}
