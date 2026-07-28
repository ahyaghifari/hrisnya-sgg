<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Koperasi & Bon — input manual per user per periode, tidak ada rumus otomatis.
 *
 * @mixin IdeHelperPayrollManualLine
 * @property string $id
 * @property string $payroll_period_id
 * @property string $user_id
 * @property string $component
 * @property numeric $amount
 * @property string|null $note
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read \App\Models\Payroll\PayrollPeriod $payrollPeriod
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine whereComponent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine wherePayrollPeriodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollManualLine whereUserId($value)
 * @mixin \Eloquent
 */
class PayrollManualLine extends Model
{
    use HasUlids;

    protected $table = 'payroll_manual_lines';

    protected $fillable = [
        'payroll_period_id',
        'user_id',
        'component',
        'amount',
        'note',
        'created_by',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
