<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Data diri kandidat (DB rekrutmen_db). Dibuat otomatis lewat JobApplication::booted()
 * saat status -> onboarding/passed. 8 tabel anak (family/education/dst) diport Fase 6b
 * buat tampilan lengkap di Filament ApplicantDetailPage.
 */
class Candidate extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'recruitment';

    protected $fillable = [
        'user_id', 'job_application_id', 'name', 'nickname', 'nik', 'no_kk',
        'blood_type', 'body_height_weight', 'religion', 'nationality', 'status_marital',
        'marriage_date', 'mother_name', 'ktp_address', 'village', 'district', 'city',
        'province', 'social_media_accounts', 'no_bpjskes', 'faskes', 'no_bpjstk',
        'company_bpjstk', 'bsi_bank_number', 'profile_picture', 'overall_status',
        'is_onboarded',
    ];

    protected $casts = ['marriage_date' => 'date', 'is_onboarded' => 'boolean'];

    public function agreements(): HasMany
    {
        return $this->hasMany(CandidateAgreement::class);
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(CandidateFamilyMember::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(CandidateTraining::class);
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(CandidateOrganization::class);
    }

    public function workExperiences(): HasMany
    {
        return $this->hasMany(CandidateWorkExperience::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(CandidateReference::class);
    }

    public function additionalInfo(): HasOne
    {
        return $this->hasOne(CandidateAdditionalInfo::class);
    }

    public function selfAssessment(): HasOne
    {
        return $this->hasOne(CandidateSelfAssessment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }
}
