<?php

namespace App\Models\Job;

use App\Enum\Recruitment\JobOpeningStatus;
use App\Models\Candidate\CandidateJobApplications;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOpenings extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'job_position_id',
        'alias',
        'quota',
        'max_applicants',
        'application_deadline',
        'is_active',
        'opened_at',
        'description',
        'requirements',
    ];

    /** Nama tampilan candidate-facing - fallback ke nama jabatan asli kalau alias kosong. */
    public function getDisplayNameAttribute(): string
    {
        return $this->alias ?: ($this->jobPosition?->name ?? '-');
    }

    protected $casts = [
        'opened_at' => 'datetime',
        'application_deadline' => 'date',
    ];

    /** Status portal (Menunggu Dibuka/Sedang Dibuka/Sudah Ditutup) — turunan dari `opened_at` +
     * `is_active`, bukan kolom sendiri. `is_active` tetap dipakai apa adanya oleh admin Filament. */
    public function openingStatus(): JobOpeningStatus
    {
        if (! $this->opened_at) {
            return JobOpeningStatus::WAITING;
        }

        return $this->is_active ? JobOpeningStatus::OPEN : JobOpeningStatus::CLOSED;
    }

    public function jobPosition()
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id', 'id');
    }

    public function applicants()
    {
        return $this->hasMany(CandidateJobApplications::class, 'job_vacancy_id', 'id');
    }
}
