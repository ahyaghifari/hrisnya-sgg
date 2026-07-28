<?php

namespace App\Models\Job;

use App\Enum\Recruitment\JobOpeningStatus;
use App\Models\Candidate\CandidateJobApplications;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperJobOpenings
 * @property string $id
 * @property string $company_id
 * @property string $job_position_id
 * @property int $quota
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $opened_at
 * @property string|null $description
 * @property string|null $requirements
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CandidateJobApplications> $applicants
 * @property-read int|null $applicants_count
 * @property-read Companies $company
 * @property-read JobPositions|null $jobPosition
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereJobPositionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereOpenedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereQuota($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereRequirements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOpenings withoutTrashed()
 * @mixin \Eloquent
 */
class JobOpenings extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'job_position_id',
        'quota',
        'is_active',
        'opened_at',
        'description',
        'requirements',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
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
