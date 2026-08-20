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
use Illuminate\Database\Eloquent\Builder;
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

        // Default query User buang 4 akun yang bukan karyawan (Direktur, istri Direktur, 2x
        // Super Admin) - jadi tidak perlu panggil scope manual di tiap tempat yang menarik
        // daftar karyawan. Login tetap jalan buat 4 akun ini karena
        // App\Auth\KaryawanAwareUserProvider (config('auth.providers.users.driver')) bypass
        // scope ini lewat withoutGlobalScope('non_karyawan') pas retrieve buat auth.
        static::addGlobalScope('non_karyawan', function (Builder $builder) {
            $builder->whereNotIn('users.id', self::NON_KARYAWAN_IDS);
        });

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

    /**
     * Kontrak paling baru YANG SUDAH MULAI (contract_start_date <= hari ini) - pola sama
     * activeJobPosition() di bawah. Filter tanggal ini penting: kalau tidak, baris kontrak yang
     * dibuat duluan dengan contract_start_date di masa depan (transisi belum efektif jalan)
     * keliru dianggap "current" sebelum waktunya.
     */
    public function currentContract(): HasOne
    {
        return $this->hasOne(\App\Models\User\UserContract::class, 'user_id', 'id')
            ->where('contract_start_date', '<=', now())
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
        // Jalur utama: role "Admin"/"Super Admin" literal (konvensi nama role project ini,
        // lihat README). Non-Admin/Super-Admin cuma boleh masuk /admin kalau eksplisit dikasih
        // permission AKSES_PANEL_ADMIN - bukan otomatis (2026-08-08, sebelumnya method ini
        // `return true` tanpa syarat sama sekali, siapa saja yang login bisa buka /admin).
        if ($this->hasRole(['Admin', 'Super Admin'])) {
            return true;
        }

        return $this->can(\App\Enum\CustomPermissionEnum::AKSES_PANEL_ADMIN->value);
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

    /** Widget "Jadwal Shift Saya" (Dashboard) & halaman kalender penuh (JadwalSayaPage) cuma
     * relevan buat karyawan yang beneran punya jadwal tipe Shift - dicek dari data jadwal
     * langsung (bukan permission JADWAL_SHIFT_SAYA lagi, itu ternyata tidak pernah di-assign ke
     * role manapun jadi widget-nya tidak pernah muncul biarpun jadwalnya shift, instruksi user
     * 2026-08-15: lepas gate permission, validasi jadwal saja). Sama pola resolusi dengan
     * ScheduleResolver (roster ScheduleShiftAssignment menang, fallback ScheduleAssignment). */
    public function hasShiftSchedule(): bool
    {
        if (\App\Models\Shift\ScheduleShiftAssignment::where('user_id', $this->id)->exists()) {
            return true;
        }

        $assignment = \App\Models\Shift\ScheduleAssignment::resolveForUser($this, now()->toDateString());

        return $assignment && $assignment->schedule->schedule_type === 'shift';
    }

    /** Karyawan boleh lihat halaman "Detail Jadwal" (`ScheduleDetailPage`) buat 1 Schedule
     * spesifik walau bukan admin (bukan punya View:Schedule) kalau dia beneran dipakaikan
     * jadwal itu - lewat ScheduleAssignment (Fixed/Flexible) langsung, atau lewat
     * ScheduleShiftAssignment yang shift_type-nya nunjuk ke Schedule ini (Shift). Dipakai
     * SchedulePolicy::view() & ScheduleShiftRosterTab (2026-08-15) - karyawan shift bisa lihat
     * roster jadwalnya sendiri DAN teman-teman se-jadwal, cuma tidak bisa edit/generate/import
     * (itu tetap KELOLA_JADWAL_SHIFT, dicek terpisah). */
    public function isAssignedToSchedule(\App\Models\Shift\Schedule $schedule): bool
    {
        if (\App\Models\Shift\ScheduleAssignment::where('user_id', $this->id)->where('schedule_id', $schedule->id)->exists()) {
            return true;
        }

        return \App\Models\Shift\ScheduleShiftAssignment::where('user_id', $this->id)
            ->whereHas('shiftType', fn ($q) => $q->where('schedule_id', $schedule->id))
            ->exists();
    }

    /** ID akun yang bukan karyawan (Presiden Direktur, Sekretaris Direktur, 2x Super Admin) -
     * dibuang dari User::query() lewat global scope 'non_karyawan' di boot(). */
    public const NON_KARYAWAN_IDS = [
        '01kd7ak9xv7tk1fzbeyraxfpze', // presiden direktur
        '01kn1dtp3y66yy00gmwvt2m2e1', // sekretaris direktur
        '01k8271ym5edqp4jm1kvwrdrr0', // Super Admin
        '01kd7ak92rg69yksst3js4apx2', // Super Admin
    ];

    /** Subset NON_KARYAWAN_IDS (Presiden Direktur & Sekretaris Direktur SAJA, TIDAK termasuk
     * 2 akun Super Admin) yang tetap boleh punya payroll - dipakai PayrollService::
     * generatePayroll() & CreatePayroll.php buat menyertakan mereka lewat
     * withoutGlobalScope('non_karyawan') secara eksplisit, instruksi user 2026-08-11. Earning
     * (Gaji Pokok dkk) tetap dihitung normal via Pangkat, potongan biasa otomatis Rp0 (tidak
     * ada AttendanceRecords/Koperasi dsb), kecuali baris manual di ExecutiveAllowance. */
    public const PAYROLL_ELIGIBLE_EXECUTIVE_IDS = [
        '01kd7ak9xv7tk1fzbeyraxfpze', // presiden direktur
        '01kn1dtp3y66yy00gmwvt2m2e1', // sekretaris direktur
    ];

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
