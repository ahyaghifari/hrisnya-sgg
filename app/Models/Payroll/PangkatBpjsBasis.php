<?php

namespace App\Models\Payroll;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Pangkats;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPangkatBpjsBasis
 * @property string $id
 * @property string $pangkat_id
 * @property string $bpjs_basis
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Pangkats $pangkat
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatBpjsBasis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatBpjsBasis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatBpjsBasis query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatBpjsBasis whereBpjsBasis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatBpjsBasis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatBpjsBasis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatBpjsBasis wherePangkatId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatBpjsBasis whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PangkatBpjsBasis extends Model
{
    use HasUlids;
    use LogsAuditChanges;

    protected $table = 'pangkat_bpjs_bases';

    protected $fillable = [
        'pangkat_id',
        'bpjs_basis',
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
        return 'bpjs:' . $this->pangkat_id;
    }

    protected function auditLabel(): ?string
    {
        return 'BPJS — ' . ($this->pangkat?->name ?? '-');
    }

    protected function auditWatchedAttribute(): string
    {
        return 'bpjs_basis';
    }
}
