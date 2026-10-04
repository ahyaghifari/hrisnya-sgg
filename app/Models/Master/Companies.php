<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Companies extends Model
{
    use HasUlids;

    /** Kode company holding (dipakai AttendanceSettingsSeeder buat override khusus) — belum
     * ada kolom eksplisit "tipe company" di m_companies, jadi identifikasi holding tetap
     * lewat kode ini, bukan hardcode ULID id (lihat RoleSeeder::HOLDING_COMPANY_ID yang beda
     * pola, sengaja tidak disatukan di sini supaya tidak mengubah perilaku seeder lama). */
    public const HOLDING_CODE = '0000';

    protected $table = "m_companies";

    protected $fillable = [
        'code',
        'name',
        'letter_name',
        'logo',
        'location',
        'is_active',
    ];

    /** URL logo di disk public, null kalau belum diunggah. */
    public function logoUrl(): ?string
    {
        return $this->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo) : null;
    }

    /** Nama untuk surat resmi (SP, Berita Acara); fallback ke nama biasa. */
    public function letterName(): string
    {
        return $this->letter_name ?: $this->name;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }

            if (empty($model->code)) {
                $lastCode = self::orderBy('code', 'desc')->first();
                $newCode = $lastCode ? (intval($lastCode->code) + 1) : 1;
                $model->code = str_pad($newCode, 4, '0', STR_PAD_LEFT);
            }
        });

        // Sync perusahaan ke DB recruitment (shared DB) tiap simpan — biar tidak perlu dibuat
        // manual di sana dulu sebelum lowongan bisa di-push. Non-fatal (lihat RecruitmentSync).
        static::saved(function (Companies $model) {
            app(\App\Services\Rekrutmen\RecruitmentSync::class)->pushCompany($model);
        });
    }

    public function orgStructure()
    {
        return $this->hasMany(OrgStructure::class, 'company_id', 'id');
    }

    public function payrollComponents()
    {
        return $this->hasMany(\App\Models\Payroll\CompanyPayrollComponent::class, 'company_id');
    }

    /**
     * Get departments (level 1) for this company
     */
    public function departments()
    {
        return $this->hasMany(OrgStructure::class, 'company_id', 'id')
            ->where('level', \App\Enum\OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->whereNull('parent_id')
            ->with(['head', 'children']);
    }

    public function jobPositions()
    {
        return $this->hasMany(JobPositions::class, 'company_id', 'id');
    }

    /** Jabatan Direktur perusahaan ini (1 baris di company_directors). Resolusi lewat
     * App\Services\CompanyDirectorService — jangan baca job_position_id-nya langsung dari fitur. */
    public function companyDirector()
    {
        return $this->hasOne(CompanyDirector::class, 'company_id', 'id');
    }

    public function jobEvaluations()
    {
        return $this->hasMany(JobEvaluationCompanies::class, 'company_id', 'id');
    }

}
