<?php

namespace App\Models\Payroll;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kategori Omset (Rawat Inap, Rawat Jalan, dst), per company — dipakai sebagai baris tetap
 * di halaman input Omset Rumah Sakit (Jasa Pelayanan).
 */
class OmsetCategory extends Model
{
    use HasUlids;
    use LogsAuditChanges;

    protected $table = 'omset_categories';

    protected $fillable = [
        'company_id',
        'name',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    protected function auditModule(): string
    {
        return 'payroll_mapping';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->company_id;
    }

    protected function auditKey(): string
    {
        return 'omset_category:' . $this->id;
    }

    protected function auditLabel(): ?string
    {
        return 'Kategori Omset — ' . $this->name;
    }

    protected function auditWatchedAttribute(): string
    {
        return 'name';
    }
}
