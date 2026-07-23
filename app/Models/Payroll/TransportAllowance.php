<?php

namespace App\Models\Payroll;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Kelas;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTransportAllowance
 */
class TransportAllowance extends Model
{
    use HasUlids;
    use LogsAuditChanges;

    protected $table = 'transport_allowances';

    protected $fillable = [
        'kelas_id',
        'amount',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    protected function auditModule(): string
    {
        return 'payroll_mapping';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->kelas?->company_id;
    }

    protected function auditKey(): string
    {
        return 'transport:' . $this->kelas_id;
    }

    protected function auditLabel(): ?string
    {
        return 'Transportasi — ' . ($this->kelas?->name ?? '-');
    }

    protected function auditWatchedAttribute(): string
    {
        return 'amount';
    }
}
