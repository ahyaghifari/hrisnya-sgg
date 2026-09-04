<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Perusahaan di DB `rekrutmen_db` — SET TERPISAH dari `m_companies` HRIS (PK bigint, kolom
 * `hris_code` jadi jembatan ke HRIS). Port dari App\Models\Company project `rekrutmen`.
 */
class Company extends Model
{
    protected $connection = 'recruitment';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_mcu' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function jobOpenings(): HasMany
    {
        return $this->hasMany(JobOpening::class);
    }

    public function formSections(): HasMany
    {
        return $this->hasMany(FormSection::class)->orderBy('sort_order');
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(CompanyAgreement::class)->orderBy('sort_order');
    }
}
