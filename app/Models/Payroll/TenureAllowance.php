<?php

namespace App\Models\Payroll;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bracket Tunjangan Masa Kerja (tahun kerja -> nominal, per company).
 *
 * @mixin IdeHelperTenureAllowance
 * @property string $id
 * @property string $company_id
 * @property int $min_years
 * @property int|null $max_years
 * @property numeric $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance whereMaxYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance whereMinYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenureAllowance whereUpdatedAt($value)
 * @mixin \Eloquent
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
