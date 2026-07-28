<?php

namespace App\Models\Payroll;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Kelas;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTransportAllowance
 * @property string $id
 * @property string $kelas_id
 * @property numeric $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Kelas $kelas
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransportAllowance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransportAllowance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransportAllowance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransportAllowance whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransportAllowance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransportAllowance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransportAllowance whereKelasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransportAllowance whereUpdatedAt($value)
 * @mixin \Eloquent
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
