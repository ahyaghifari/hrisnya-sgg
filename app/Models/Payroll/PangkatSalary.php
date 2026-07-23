<?php

namespace App\Models\Payroll;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Pangkats;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPangkatSalary
 */
class PangkatSalary extends Model
{
    use HasUlids;
    use LogsAuditChanges;

    protected $table = 'pangkat_salaries';

    protected $fillable = [
        'pangkat_id',
        'base_salary',
    ];

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(Pangkats::class, 'pangkat_id');
    }

    protected function auditModule(): string
    {
        return 'payroll_mapping';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->pangkat?->company_id;
    }

    protected function auditKey(): string
    {
        return 'gaji_pokok:' . $this->pangkat_id;
    }

    protected function auditLabel(): ?string
    {
        return 'Gaji Pokok — ' . ($this->pangkat?->name ?? '-');
    }

    protected function auditWatchedAttribute(): string
    {
        return 'base_salary';
    }
}
