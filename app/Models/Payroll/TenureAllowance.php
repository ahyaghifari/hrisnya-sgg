<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bracket Tunjangan Masa Kerja (tahun kerja -> nominal, per company).
 */
class TenureAllowance extends Model
{
    use HasUlids;
    use LogsAuditChanges;

    protected $table = 'tenure_allowances';

    protected $fillable = [
        'company_id',
        'min_years',
        'max_years',
        'amount',
    ];

    protected $casts = [
        'amount' => PayrollEncrypted::class,
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
        return 'tenure:' . $this->id;
    }

    protected function auditLabel(): ?string
    {
        return 'Tunjangan Masa Kerja — ' . $this->min_years . '-' . ($this->max_years ?? '∞') . ' tahun';
    }

    protected function auditWatchedAttribute(): string
    {
        return 'amount';
    }
}
