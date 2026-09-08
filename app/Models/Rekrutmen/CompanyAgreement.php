<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAgreement extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['company_id', 'title', 'content', 'per_opening', 'sort_order'];

    protected $casts = ['per_opening' => 'boolean'];

    protected static function booted(): void
    {
        // Kolom `content` NOT NULL — persetujuan "diisi per lowongan" naskahnya kosong di sini.
        static::saving(fn (self $m) => $m->content ??= '');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
