<?php

namespace App\Models\Payroll;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Pangkats;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPangkatSalary
 * @property string $id
 * @property string $pangkat_id
 * @property numeric $base_salary
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Pangkats $pangkat
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatSalary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatSalary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatSalary query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatSalary whereBaseSalary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatSalary whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatSalary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatSalary wherePangkatId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PangkatSalary whereUpdatedAt($value)
 * @mixin \Eloquent
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
