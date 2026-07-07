<?php

namespace App\Models\Payroll;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperPayrollPeriod
 */
class PayrollPeriod extends Model
{
    use HasUlids;
    use BelongsToCompany;

    protected $table = 'payroll_periods';

    protected $fillable = [
        'company_id',
        'start_date',
        'end_date',
        'processed_at',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }
}
