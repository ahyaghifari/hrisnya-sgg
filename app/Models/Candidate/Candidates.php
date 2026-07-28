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
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $phone_number
 * @property string $password
 * @property string|null $email_verified_at
 * @property string|null $remember_token
 * @property string|null $nickname
 * @property string|null $blood_type
 * @property string|null $body_height_weight
 * @property string|null $marriage_date
 * @property string|null $social_media_accounts
 * @property string|null $last_education
 * @property string|null $nationality
 * @property string|null $gender
 * @property string|null $religion
 * @property string|null $no_kk
 * @property string|null $nik
 * @property string|null $birth_place
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property string|null $status_marital
 * @property string|null $ktp_address
 * @property string|null $domicile_address
 * @property string|null $village
 * @property string|null $district
 * @property string|null $city
 * @property string|null $province
 * @property string|null $mother_name
 * @property string|null $education
 * @property string|null $major
 * @property string|null $university
 * @property string|null $graduation_year
 * @property string|null $no_bpjskes
 * @property string|null $faskes
 * @property string|null $no_bpjstk
 * @property string|null $company_bpjstk
 * @property string|null $bsi_bank_number
 * @property string|null $profile_picture
 * @property bool $is_onboarded
 * @property string|null $overall_status
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\CandidateAdditionalInfos|null $additionalInfos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidate\CandidateDocuments> $documents
 * @property-read int|null $documents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidate\CandidateEducations> $educations
 * @property-read int|null $educations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidate\CandidateFamilyMembers> $familyMembers
 * @property-read int|null $family_members_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidate\CandidateOrganizations> $organizations
 * @property-read int|null $organizations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidate\CandidateReferences> $references
 * @property-read int|null $references_count
 * @property-read \App\Models\Candidate\CandidateSelfAssessments|null $selfAssessments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidate\CandidateTrainings> $trainings
 * @property-read int|null $trainings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidate\CandidateWorkExperience> $workExperiences
 * @property-read int|null $work_experiences_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereBirthPlace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereBloodType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereBodyHeightWeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereBsiBankNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereCompanyBpjstk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereDistrict($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereDomicileAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereEducation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereFaskes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereGraduationYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereIsOnboarded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereKtpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereLastEducation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereMajor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereMarriageDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereMotherName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereNik($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereNoBpjskes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereNoBpjstk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereNoKk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereOverallStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereProfilePicture($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereProvince($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereReligion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereSocialMediaAccounts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereStatusMarital($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereUniversity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates whereVillage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Candidates withoutTrashed()
 * @mixin \Eloquent
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
            'birth_date' => 'date',
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
