<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Concerns\LogsFieldAuditChanges;
use App\Models\Master\Companies;
use App\Models\Payroll\BpjsDependent;
use App\Models\Payroll\UserKoperasi;
use App\Models\Shift\ShiftScheduleAssignments;
use App\Models\User\UserDocuments;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @mixin IdeHelperUser
 * @property string $id
 * @property string $nip
 * @property string|null $company_id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $type_nakes
 * @property int $is_active
 * @property string|null $fcm_token
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read UserJobPositions|null $activeJobPosition
 * @property-read Companies|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserJobPositions> $jobPositions
 * @property-read int|null $job_positions_count
 * @property-read UserKoperasi|null $koperasi
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read UserProfiles|null $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \NotificationChannels\WebPush\PushSubscription> $pushSubscriptions
 * @property-read int|null $push_subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShiftScheduleAssignments> $shiftScheduleAssignments
 * @property-read int|null $shift_schedule_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserDocuments> $userCompaniesDocuments
 * @property-read int|null $user_companies_documents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserDocuments> $userPersonalDocuments
 * @property-read int|null $user_personal_documents_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFcmToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTypeNakes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 * @mixin \Eloquent
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasPushSubscriptions, HasUlids, HasRoles, HasApiTokens, SoftDeletes;
    use LogsFieldAuditChanges;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nip',
        'company_id',
        'type_nakes',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }

            if (empty($model->nip)) {
                $date = date('Ymd');
                $userCount = static::count() + 1;
                $index = str_pad($userCount, 3, '0', STR_PAD_LEFT);
                $random = mt_rand(10, 99);
                $model->nip = $date . $index . $random;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function profile()
    {
        return $this->hasOne(UserProfiles::class, 'user_id', 'id');
    }

    public function koperasi()
    {
        return $this->hasOne(UserKoperasi::class, 'user_id', 'id');
    }

    public function bpjsDependent()
    {
        return $this->hasOne(BpjsDependent::class, 'user_id', 'id');
    }

    // Dokumen yang diunggah oleh user itu sendiri
    public function userPersonalDocuments()
    {
        return $this->hasMany(UserDocuments::class, 'user_id', 'id')
            ->where('uploaded_by_user', true);
    }

    // Dokumen yang diunggah oleh perusahaan untuk user tersebut
    public function userCompaniesDocuments()
    {
        return $this->hasMany(UserDocuments::class, 'user_id', 'id')
            ->where('uploaded_by_user', false);
    }

    public function jobPositions(): HasMany
    {
        return $this->hasMany(UserJobPositions::class, 'user_id', 'id');
    }

    /** Riwayat kontrak (1 baris = 1 periode/tahun kontrak yang sudah disetujui), lihat UserContract. */
    public function contracts(): HasMany
    {
        return $this->hasMany(\App\Models\User\UserContract::class, 'user_id', 'id');
    }

    /** Kontrak paling baru (periode aktif saat ini) - pola sama activeJobPosition() di bawah. */
    public function currentContract(): HasOne
    {
        return $this->hasOne(\App\Models\User\UserContract::class, 'user_id', 'id')
            ->latestOfMany('contract_start_date');
    }

    public function activeJobPosition(): HasOne
    {
        return $this->hasOne(UserJobPositions::class, 'user_id', 'id')
            ->where('status', 'active')
            ->latestOfMany('start_date');
    }

    public function shiftScheduleAssignments(): HasMany
    {
        return $this->hasMany(ShiftScheduleAssignments::class, 'user_id', 'id');
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return true;
    }

    public function hasSubordinates(): bool
    {
        return (bool) $this->activeJobPosition?->jobPosition?->children()->exists();
    }

    public function canBrowseAllCompanies(): bool
    {
        return $this->hasRole('Direktur') && \App\Helpers\PermissionHelper::hasGlobalRole($this);
    }

    /** Boleh lihat/pilih filter perusahaan lain di portal, bukan cuma company sendiri. */
    public function canFilterByCompany(): bool
    {
        return $this->can(\App\Enum\CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value);
    }

    /**
     * Dulu ditulis ulang di dua tempat (nav.blade.php & MyEmployees::mount()) dengan kondisi
     * yang sama persis — disatukan di sini supaya nav dan guard halaman tidak bisa beda sendiri.
     */
    public function canAccessKaryawanMenu(): bool
    {
        return $this->hasSubordinates() || $this->canBrowseAllCompanies();
    }

    protected function auditModule(): string
    {
        return 'data_karyawan';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->company_id;
    }

    /** Sama dengan UserProfiles::auditRecordKey() - keduanya dituliskan dengan prefix user_id
     * yang sama supaya 1 query bisa gabungkan riwayat akun + profil 1 orang, lihat
     * EmployeeAttendanceStatistics::getAuditHistoryProperty(). */
    protected function auditRecordKey(): string
    {
        return $this->id;
    }

    /** password/remember_token sengaja tidak diikutkan (jangan pernah log hash password),
     * begitu juga type_nakes (jarang dipakai/tidak relevan buat karyawan biasa). */
    protected function auditFieldLabels(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'nip' => 'NIP',
            'company_id' => 'Perusahaan',
            'is_active' => 'Status Aktif',
        ];
    }
}
