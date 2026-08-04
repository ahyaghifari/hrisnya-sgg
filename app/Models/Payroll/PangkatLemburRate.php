<?php

namespace App\Models\Payroll;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Pangkats;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PangkatLemburRate extends Model
{
    use HasUlids;
    use LogsAuditChanges;

    protected $table = 'pangkat_lembur_rates';

    protected $fillable = [
        'pangkat_id',
        'amount_per_hour',
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
        return 'lembur_rate:' . $this->pangkat_id;
    }

    protected function auditLabel(): ?string
    {
        return 'Base Rate Lembur — ' . ($this->pangkat?->name ?? '-');
    }

    protected function auditWatchedAttribute(): string
    {
        return 'amount_per_hour';
    }
}
