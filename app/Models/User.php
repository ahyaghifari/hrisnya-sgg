<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Models\Concerns\LogsFieldAuditChanges;
use App\Models\Master\Companies;
use App\Models\Payroll\BpjsDependent;
use App\Models\Payroll\UserKoperasi;
use App\Models\Setting;
use App\Models\Shift\ShiftScheduleAssignments;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use Carbon\Carbon;
use Filament\Models\Contracts\FilamentUser;
use App\Observers\EmployeeSyncObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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

#[ObservedBy(EmployeeSyncObserver::class)]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasPushSubscriptions, HasUlids, HasRoles, HasApiTokens, SoftDeletes;
    use LogsFieldAuditChanges;

    /**
     * Data karyawan selalu ikut dimuat (satu query per batch, bukan per baris) supaya pembacaan
     * `$user->employee->...` / `full_name` di daftar & layout tidak jadi N+1. Termasuk saat User
     * dimuat lewat relasi (`$payroll->user`, `with('user')`). Kosong untuk dua akun Super Admin.
     *
     * @var list<string>
     */
    protected $with = ['employee'];

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
        'is_active',
        'profile_picture',
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
            'profile_picture' => 'array',
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

        // Dokter tidak ikut daftar karyawan biasa. Scope terpisah dari 'non_karyawan' supaya
        // route binding (resolveRouteBinding) bisa melewatinya tanpa membuka 4 akun eksekutif.
        static::addGlobalScope('dokter', function (Builder $builder) {
            $builder->whereNotIn('users.id', \App\Models\Employee\Employee::query()
                ->select('user_id')->where('is_dokter', true)->whereNotNull('user_id'));
        });

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }

            if (empty($model->rawNip())) {
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

    /*
     * name / nip / company_id: SUMBER = employees (nama_lengkap, nip, company_id).
     *
     * PEMBACAAN `$user->name|nip|company_id` diambil dari employees (accessor menimpa kolom), jatuh
     * balik ke kolom users untuk salinan yang belum terisi. PENULISAN (`create`, `update`, `fill`,
     * `$user->name = ..`) ditangkap mutator di bawah: nilainya ditahan di $pendingEmployee lalu
     * disimpan ke employees oleh EmployeeSyncObserver saat `saved`, dan - selama
     * config('identity.legacy_user_columns') true - juga ditulis ke kolom users (cermin).
     * Setelah kolom dicabut (flag false), penulisan HANYA ke employees; semua kode pemanggil
     * yang sudah ada tidak perlu diubah.
     *
     * Yang TIDAK terpengaruh: query SQL (where/orderBy/pluck/join/Auth::attempt) - pakai scope di
     * bawah (whereNip, searchName, whereCompany, orderByName). Kode yang butuh nilai SUMBER lama
     * (users.*) memakai rawName()/rawNip()/rawCompanyId(). Jangan memanggil $this->name di dalam
     * accessor/mutator ini (rekursi).
     */

    /** @var array<string, mixed> kolom employees yang menunggu disimpan: nama_lengkap | nip | company_id */
    protected array $pendingEmployee = [];

    /** Hasil deteksi skema (sekali per proses). */
    private static ?bool $legacyDetected = null;

    /** Apakah kolom users.name/nip/company_id masih ada (masa transisi)? Lihat config/identity.php. */
    public static function legacyColumns(): bool
    {
        $forced = config('identity.legacy_user_columns');

        if ($forced !== null) {
            return (bool) $forced;
        }

        if (self::$legacyDetected === null) {
            $schema = \Illuminate\Support\Facades\Schema::getFacadeRoot();

            // Tabel belum ada (belum bermigrasi): anggap masa transisi dan JANGAN simpan hasilnya.
            if (! $schema->hasTable('users')) {
                return true;
            }

            self::$legacyDetected = $schema->hasColumn('users', 'nip');
        }

        return self::$legacyDetected;
    }

    public function setNameAttribute($value): void
    {
        $this->holdForEmployee('name', 'nama_lengkap', $value);
    }

    public function setNipAttribute($value): void
    {
        $this->holdForEmployee('nip', 'nip', $value);
    }

    public function setCompanyIdAttribute($value): void
    {
        $this->holdForEmployee('company_id', 'company_id', $value);
    }

    private function holdForEmployee(string $column, string $employeeColumn, mixed $value): void
    {
        $this->pendingEmployee[$employeeColumn] = $value;

        if (static::legacyColumns()) {
            $this->attributes[$column] = $value;
        }
    }

    /** @return array<string, mixed> */
    public function pendingEmployeeAttributes(): array
    {
        return $this->pendingEmployee;
    }

    public function clearPendingEmployee(): void
    {
        $this->pendingEmployee = [];
    }

    public function getNameAttribute($value): ?string
    {
        return $this->pendingEmployee['nama_lengkap'] ?? ($this->employee?->nama_lengkap ?: $value);
    }

    public function getNipAttribute($value): ?string
    {
        return $this->pendingEmployee['nip'] ?? ($this->employee?->nip ?: $value);
    }

    public function getCompanyIdAttribute($value): ?string
    {
        return array_key_exists('company_id', $this->pendingEmployee)
            ? $this->pendingEmployee['company_id']
            : ($this->employee?->company_id ?? $value);
    }

    /** Nilai kolom users apa adanya (nilai tertahan ikut dihitung bila kolom sudah tidak ditulis). */
    public function rawName(): ?string
    {
        return $this->getAttributes()['name'] ?? ($this->pendingEmployee['nama_lengkap'] ?? null);
    }

    public function rawNip(): ?string
    {
        return $this->getAttributes()['nip'] ?? ($this->pendingEmployee['nip'] ?? null);
    }

    public function rawCompanyId(): ?string
    {
        return $this->getAttributes()['company_id'] ?? ($this->pendingEmployee['company_id'] ?? null);
    }

    /*
     * Scope query berbasis employees - PENGGANTI kolom users.name/nip/company_id di dalam query SQL
     * (where/orderBy/pencarian), supaya kolom users bisa dicabut. Pakai ini, bukan `where('nip', ..)`
     * / `where('company_id', ..)` / `orderBy('name')` pada query User. Semuanya subquery ke employees
     * (tanpa join) sehingga aman dipakai di dalam `whereHas('user', fn ($q) => $q->searchName(..))`
     * dan tidak mengubah daftar kolom/alias query pemanggil.
     */
    /** Hanya dokter (employees.is_dokter); 4 akun non-karyawan tetap terbuang. */
    public function scopeOnlyDokter(Builder $query): Builder
    {
        return $query->withoutGlobalScope('dokter')->whereIn('users.id', \App\Models\Employee\Employee::query()
            ->select('user_id')->where('is_dokter', true)->whereNotNull('user_id'));
    }

    /** Dokter tetap bisa dibuka lewat URL {record} (Lihat/Edit dari tab Dokter). */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope('dokter')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function scopeWhereNip(Builder $query, string|array $nip): Builder
    {
        return $query->whereIn('users.id', \App\Models\Employee\Employee::query()->select('user_id')->whereIn('nip', (array) $nip));
    }

    public function scopeWhereNipLike(Builder $query, string $term): Builder
    {
        return $query->whereIn('users.id', \App\Models\Employee\Employee::query()->select('user_id')->where('nip', 'like', "%{$term}%"));
    }

    /**
     * Perusahaan karyawan (employees.company_id). Pengganti `where('company_id', $id)` pada query User.
     * `null` berarti IS NULL (user tanpa perusahaan), PERSIS seperti `where('company_id', null)` yang lama.
     */
    public function scopeWhereCompany(Builder $query, string|array|null $companyId): Builder
    {
        $employees = \App\Models\Employee\Employee::query()->select('user_id');

        $companyId === null
            ? $employees->whereNull('company_id')
            : $employees->whereIn('company_id', (array) $companyId);

        return $query->whereIn('users.id', $employees);
    }

    public function scopeSearchName(Builder $query, string $term): Builder
    {
        return $query->whereIn('users.id', \App\Models\Employee\Employee::query()->select('user_id')->where('nama_lengkap', 'like', "%{$term}%"));
    }

    /** Cari nama ATAU NIP (pola pencarian daftar karyawan). */
    public function scopeSearchNameOrNip(Builder $query, string $term): Builder
    {
        return $query->whereIn('users.id', \App\Models\Employee\Employee::query()->select('user_id')
            ->where(fn ($q) => $q->where('nama_lengkap', 'like', "%{$term}%")->orWhere('nip', 'like', "%{$term}%")));
    }

    /** Pemegang jabatan aktif di level Departemen (manajer/atasan), bukan Divisi - dipakai untuk penanda tangan "Mengetahui". */
    public function scopeAtDepartmentLevel(Builder $query): Builder
    {
        return $query->whereHas('activeJobPosition.jobPosition.orgStructure', fn ($q) => $q->where('level', \App\Enum\OrgStructurLevel::LEVEL_DEPARTMENT->value));
    }

    public function scopeOrderByName(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy(
            \App\Models\Employee\Employee::query()->select('nama_lengkap')->whereColumn('employees.user_id', 'users.id')->limit(1),
            $direction,
        );
    }

    public function scopeOrderByNip(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy(
            \App\Models\Employee\Employee::query()->select('nip')->whereColumn('employees.user_id', 'users.id')->limit(1),
            $direction,
        );
    }

    /** Kompatibilitas: dulu accessor terpisah untuk nama tampilan; kini sama dengan `name`. */
    public function getFullNameAttribute(): string
    {
        return (string) $this->name;
    }

    /**
     * Path foto profil di disk public, atau null. Atribut AKUN (users.profile_picture, array path).
     * Menormalkan bentuk data lama: sebagian baris tersimpan sebagai string JSON atau string path
     * biasa, bukan array - akses `[0]` langsung pada string mengambil SATU KARAKTER pertama.
     */
    public function profilePhotoPath(): ?string
    {
        $value = $this->profile_picture;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        $path = is_array($value) ? ($value[0] ?? null) : null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /** Inisial (maks $limit huruf) dari nama, untuk avatar. Tahan terhadap spasi ganda/ujung. */
    public function initials(int $limit = 2): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->take($limit)
            ->implode('');
    }

    /** Data karyawan (employees.user_id; kosong untuk dua akun Super Admin yang bukan karyawan). */
    public function employee()
    {
        return $this->hasOne(\App\Models\Employee\Employee::class, 'user_id');
    }

    /** Kunci urut roster jadwal shift: tanggal bergabung terlama dulu (yang belum diisi di paling
     * bawah), lalu nama tanpa peduli huruf besar/kecil sebagai pembeda (2026-09-21). Eager load
     * `profile` dulu supaya tidak N+1. */
    public function rosterSortKey(): string
    {
        return (substr((string) $this->profile?->joining_date, 0, 10) ?: '9999-12-31') . '|' . mb_strtolower($this->name);
    }

    public function koperasi()
    {
        return $this->hasOne(UserKoperasi::class, 'user_id', 'id');
    }

    public function bpjsDependent()
    {
        return $this->hasOne(BpjsDependent::class, 'user_id', 'id');
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

    /** Pengajuan penilaian orientasi (self-service, bisa >1 kalau pernah "Perpanjang"). */
    public function orientationSubmissions(): HasMany
    {
        return $this->hasMany(\App\Models\OrientationSubmission::class, 'user_id', 'id');
    }

    /** Pengajuan perpanjangan kontrak (self-service). */
    public function contractSubmissions(): HasMany
    {
        return $this->hasMany(\App\Models\ContractSubmission::class, 'user_id', 'id');
    }

    /** Riwayat periode orientasi (1 baris = 1 periode/perpanjangan), lihat UserOrientation.
     *  Dibuat lewat Rekrutmen, bukan "Tambah Karyawan" manual. */
    public function orientations(): HasMany
    {
        return $this->hasMany(\App\Models\User\UserOrientation::class, 'user_id', 'id');
    }

    /** Periode orientasi terbaru yang SUDAH MULAI - pola currentContract(). */
    public function currentOrientation(): HasOne
    {
        return $this->hasOne(\App\Models\User\UserOrientation::class, 'user_id', 'id')
            ->where('start_date', '<=', now())
            ->latestOfMany('start_date');
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

        if ($panel->getId() === 'rekrutmen') {
            return $this->can(\App\Enum\CustomPermissionEnum::SISTEM_REKRUTMEN->value);
        }

        if ($panel->getId() === 'keycloak') {
            return $this->can(\App\Enum\CustomPermissionEnum::KELOLA_KEYCLOAK->value);
        }

        if ($panel->getId() === 'dokter') {
            return $this->can(\App\Enum\CustomPermissionEnum::KELOLA_DOKTER->value);
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

    /** User ini dari company holding (code '0000', lihat Companies::HOLDING_CODE) - dipakai
     * buat gerbang fitur yang cuma boleh diakses/dilihat holding, mis. root "Lainnya" di tree
     * Hak Akses (RoleAccessTreePage). */
    public function isFromHoldingCompany(): bool
    {
        return $this->company?->code === \App\Models\Master\Companies::HOLDING_CODE;
    }

    /**
     * Sama seperti canFilterByCompany(), tapi dipersempit lagi per sistem/modul lewat
     * config CompanyFilterAccess (Manajemen Pengguna > Akses Filter Perusahaan) - admin
     * centang sistem mana saja yang boleh dipakai lintas company per company+role/user.
     * Fail-closed: tanpa permission dasar, tanpa row config, atau row ada tapi sistemnya
     * tidak dicentang -> false (filter disembunyikan, dikunci ke company sendiri).
     */
    public function canFilterByCompanyForSystem(\App\Enum\CustomPermissionEnum $system): bool
    {
        if (! $this->canFilterByCompany()) {
            return false;
        }

        $roleIds = $this->roles->pluck('id')->map(fn ($id) => (string) $id);

        return \App\Models\CompanyFilterAccess::where('company_id', $this->company_id)
            ->where(function ($query) use ($roleIds) {
                $query->where(fn ($q) => $q->where('grantee_type', 'user')->where('grantee_id', $this->id))
                    ->orWhere(fn ($q) => $q->where('grantee_type', 'role')->whereIn('grantee_id', $roleIds));
            })
            ->get()
            ->contains(fn ($row) => in_array($system->value, $row->systems, true));
    }

    /** Gerbang halaman "Dashboard Anda" (route `dashboard-anda`, lihat config/portal-pages.php)
     * - boleh dibuka kalau user pegang MINIMAL SATU permission widget DASHBOARD_HC_* (bukan
     * harus semua). Widget yang tanpa permission tetap tersembunyi sendiri di dalam halaman
     * (WidgetPermissionEnum::DASHBOARD_HC_*), ini cuma nentuin apakah halamannya kebuka sama
     * sekali/tombol "Dashboard Anda" muncul di welcome card. */
    public function canAccessDashboardAnda(): bool
    {
        return collect(\App\Enum\WidgetPermissionEnum::cases())
            ->filter(fn ($case) => str_starts_with($case->value, 'dashboard_hc_'))
            ->contains(fn ($case) => $this->can($case->value));
    }

    /**
     * Dulu ditulis ulang di dua tempat (nav.blade.php & MyEmployees::mount()) dengan kondisi
     * yang sama persis — disatukan di sini supaya nav dan guard halaman tidak bisa beda sendiri.
     */
    public function canAccessKaryawanMenu(): bool
    {
        return $this->hasSubordinates() || $this->canBrowseAllCompanies();
    }

    /** Gate nav self-service "/sikartap" (mulai pengajuan karyawan tetap) - cuma karyawan yang
     * kontraknya AKTIF SAAT INI berkategori 'kontrak' (§ EmployeeTypeCategory) yang boleh
     * mengajukan, bukan siapa saja yang login. Orientasi/Tetap tidak relevan buat alur ini.
     * Cek tipe karyawan SAAT INI juga (bukan cuma baris kontrak) - karyawan yang sudah diangkat
     * jadi Tetap bisa masih punya baris user_contracts aktif yang belum berakhir; tanpa cek ini
     * dia keliru masih lihat menu Sikartap. */
    public function isKontrakKaryawan(): bool
    {
        return $this->currentContract?->employeeType?->category === \App\Enum\EmployeeTypeCategory::KONTRAK
            && $this->profile?->employeeType?->category === \App\Enum\EmployeeTypeCategory::KONTRAK;
    }

    /** Gate tombol "Ajukan Pengangkatan Karyawan Tetap" di tab Karir Profil Saya DAN pembuatan
     * pengajuan baru (PengajuanKaryawanTetapPage::startApplication). Harus Kontrak
     * (isKontrakKaryawan()), lalu 2 mode per company (KepegawaianSettingEnum::
     * PENGANGKATAN_MODE_BUKA_TUTUP, diatur lewat select di Pengaturan Kepegawaian):
     * - 'buka_tutup': masa kerja DIABAIKAN, eligible cuma selama PENGANGKATAN_DIBUKA (toggle-nya
     *   di Dashboard Sikartap, bukan di Pengaturan Kepegawaian - lihat DashboardSikartapPage).
     * - 'masa_kerja' (default): masa kerja sejak Tanggal Bergabung harus sudah lewat ambang
     *   MASA_KERJA_MINIMAL_PENGANGKATAN_BULAN (default 15 bulan). Tanpa Tanggal Bergabung dianggap
     *   belum eligible - tidak ada dasar hitung masa kerja. */
    public function isEligibleForPengangkatanTetap(): bool
    {
        if (! $this->isKontrakKaryawan()) {
            return false;
        }

        $mode = Setting::resolve(
            KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP->value,
            $this->company_id,
            default: KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP->default(),
        );

        if ($mode === 'buka_tutup') {
            return (bool) Setting::resolve(
                KepegawaianSettingEnum::PENGANGKATAN_DIBUKA->value,
                $this->company_id,
                default: filter_var(KepegawaianSettingEnum::PENGANGKATAN_DIBUKA->default(), FILTER_VALIDATE_BOOLEAN),
            );
        }

        $joinDate = $this->profile?->joining_date;
        if (! $joinDate) {
            return false;
        }

        $minBulan = (int) Setting::resolve(
            KepegawaianSettingEnum::MASA_KERJA_MINIMAL_PENGANGKATAN_BULAN->value,
            $this->company_id,
            default: KepegawaianSettingEnum::MASA_KERJA_MINIMAL_PENGANGKATAN_BULAN->default(),
        );

        if ($minBulan <= 0) {
            return true;
        }

        $masaKerjaBulan = Carbon::parse($joinDate)->diffInMonths(now());

        return $masaKerjaBulan >= $minBulan;
    }

    /** Gate tombol "Ajukan Perpanjangan Kontrak" di Profil Saya - reuse persis kondisi
     * KontrakSayaPage::getCanStartNewProperty() (jendela lead-time sebelum kontrak berakhir +
     * belum ada pengajuan buat kontrak aktif saat ini), supaya tombolnya nggak nongol duluan
     * baru ternyata pas diklik belum waktunya/sudah pernah mengajukan. */
    public function isEligibleForPerpanjanganKontrak(): bool
    {
        if (! $this->isKontrakKaryawan()) {
            return false;
        }

        $contract = $this->currentContract;
        if (! $contract || \App\Models\ContractSubmission::where('user_contract_id', $contract->id)->exists()) {
            return false;
        }

        $leadDays = (int) Setting::resolve(
            KepegawaianSettingEnum::PENGAJUAN_KONTRAK_LEAD_HARI->value,
            $this->company_id,
            default: KepegawaianSettingEnum::PENGAJUAN_KONTRAK_LEAD_HARI->default(),
        );

        if (! $contract->contract_end_date || $leadDays <= 0) {
            return true;
        }

        $opensAt = Carbon::parse($contract->contract_end_date)->copy()->subDays($leadDays)->startOfDay();

        return now()->gte($opensAt);
    }

    /** Gate nav self-service "/orientasi" (mengajukan penilaian orientasi) - karyawan yang
     * status kepegawaiannya SAAT INI berkategori 'orientasi'. Sumber kebenaran = tipe karyawan
     * (profile.employee_type), BUKAN ada/tidaknya baris user_orientations - ledger periode itu
     * soal durasi, bukan soal "sedang orientasi atau tidak". Karyawan lama tanpa baris ledger
     * tetap harus bisa buka halaman ini. Beda dari /kepegawaian/orientasi (sisi HR). */
    public function isOrientasiKaryawan(): bool
    {
        return $this->profile?->employeeType?->category === \App\Enum\EmployeeTypeCategory::ORIENTASI;
    }

    /** Gate halaman /sikartap/mcu (antrian) — OR dari 4 permission INPUT_MCU_* (bukan
     * config('portal-pages') 'permission' tunggal, karena siapapun yang punya SALAH SATU
     * dari 4 form MCU tetap boleh lihat antrian, gate per-tombol ada di view-nya sendiri). */
    public function canInputSikartapMcu(): bool
    {
        return $this->can(\App\Enum\CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value)
            || $this->can(\App\Enum\CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value)
            || $this->can(\App\Enum\CustomPermissionEnum::INPUT_MCU_RADIOLOGI->value)
            || $this->can(\App\Enum\CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER->value);
    }

    /** Gerbang modul MCU berdiri sendiri (dropdown nav "MCU" + akses panel rekrutmen khusus
     * halaman MCU) — SISTEM_MCU eksplisit ATAU sudah pegang salah satu permission isi/edit MCU
     * (OR ke cek lama biar staf klinis existing tidak kehilangan akses sebelum SISTEM_MCU
     * di-assign). */
    public function canAccessMcuModule(): bool
    {
        return $this->can(\App\Enum\CustomPermissionEnum::SISTEM_MCU->value)
            || $this->canInputSikartapMcu()
            || $this->can(\App\Enum\CustomPermissionEnum::EDIT_INPUT_MCU->value);
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
     * (itu butuh Update:ShiftScheduleAssignments, dicek terpisah di ScheduleShiftRosterTab). */
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
     * EmployeeStatistics::getAuditHistoryProperty(). */
    protected function auditRecordKey(): string
    {
        return $this->id;
    }

    /** password/remember_token sengaja tidak diikutkan (jangan pernah log hash password),
     * (status nakes ada di jabatan: JobPositions::is_nakes). */
    protected function auditFieldLabels(): array
    {
        // Nama, NIP, dan perusahaan TIDAK lagi dicatat di sini: sumbernya employees, dan audit-nya
        // dicatat Employee (kunci `{user_id}:name|nip|company_id` sama, riwayat tetap satu aliran).
        // Mencatatnya di dua tempat menghasilkan baris ganda selama kolom users masih ada, dan berhenti
        // total setelah kolom dicabut.
        return [
            'email' => 'Email',
            'is_active' => 'Status Aktif',
        ];
    }
}
