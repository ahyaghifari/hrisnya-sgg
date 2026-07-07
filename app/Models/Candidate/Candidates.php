<?php

namespace App\Models\Candidate;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @mixin IdeHelperCandidates
 */
class Candidates extends Authenticatable implements CanResetPassword, MustVerifyEmail, FilamentUser
{
    use HasUlids, SoftDeletes, Notifiable, MustVerifyEmailTrait;

    protected $table = 'candidates';

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'password',
        'gender',
        'religion',
        'no_kk',
        'nik',
        'birth_place',
        'birth_date',
        'status_marital',
        'ktp_address',
        'domicile_address',
        'village',
        'district',
        'city',
        'province',
        'mother_name',
        'education',
        'major',
        'university',
        'graduation_year',
        'no_bpjskes',
        'faskes',
        'no_bpjstk',
        'company_bpjstk',
        'bsi_bank_number',
        'profile_picture',
        'is_onboarded',
        'overall_status',
        'nickname',
        'blood_type',
        'body_height_weight',
        'marriage_date',
        'social_media_accounts',
        'last_education',
        'nationality',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_onboarded' => 'boolean',
        ];
    }

    public function sendPasswordResetNotification($token)
    {
        return parent::sendPasswordResetNotification($token);
    }

    public function documents()
    {
        return $this->hasMany(CandidateDocuments::class, 'candidate_id');
    }

    public function familyMembers()
    {
        return $this->hasMany(CandidateFamilyMembers::class, 'candidate_id');
    }

    public function educations()
    {
        return $this->hasMany(CandidateEducations::class, 'candidate_id');
    }

    public function trainings()
    {
        return $this->hasMany(CandidateTrainings::class, 'candidate_id');
    }

    public function organizations()
    {
        return $this->hasMany(CandidateOrganizations::class, 'candidate_id');
    }

    public function workExperiences()
    {
        return $this->hasMany(CandidateWorkExperience::class, 'candidate_id');
    }

    public function references()
    {
        return $this->hasMany(CandidateReferences::class, 'candidate_id');
    }

    public function additionalInfos()
    {
        return $this->hasOne(CandidateAdditionalInfos::class, 'candidate_id');
    }

    public function selfAssessments()
    {
        return $this->hasOne(CandidateSelfAssessments::class, 'candidate_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return  true;
    }
}
