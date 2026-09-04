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

    protected $fillable = [
        'hris_code', 'name', 'logo_path', 'hero_image_path', 'brand_color',
        'email', 'phone', 'is_active', 'requires_mcu',
    ];

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
}
