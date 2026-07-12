<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Master\Companies;
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
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasPushSubscriptions, HasUlids, HasRoles, HasApiTokens, SoftDeletes;

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
        return $this->hasRole(['Admin', 'Super Admin']);
    }

    public function hasSubordinates(): bool
    {
        return (bool) $this->activeJobPosition?->jobPosition?->children()->exists();
    }

    public function canBrowseAllCompanies(): bool
    {
        return $this->hasRole('Direktur') && \App\Helpers\PermissionHelper::hasGlobalRole($this);
    }

    /**
     * Dulu ditulis ulang di dua tempat (nav.blade.php & MyEmployees::mount()) dengan kondisi
     * yang sama persis — disatukan di sini supaya nav dan guard halaman tidak bisa beda sendiri.
     */
    public function canAccessKaryawanMenu(): bool
    {
        return $this->hasSubordinates() || $this->canBrowseAllCompanies();
    }
}
