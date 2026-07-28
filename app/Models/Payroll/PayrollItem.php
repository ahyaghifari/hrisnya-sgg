<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPayrollItem
 * @property string $id
 * @property string $payroll_id
 * @property string $name
 * @property string $type
 * @property numeric|null $quantity
 * @property numeric|null $unit_amount
 * @property numeric $amount
 * @property string|null $unit
 * @property string|null $note
 * @property string|null $source_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Payroll\Payroll $payroll
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem wherePayrollId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereUnitAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PayrollItem extends Model
{
    use HasUlids;

    protected $table = 'payroll_items';

    protected $fillable = [
        'payroll_id',
        'name',
        'type',
        'calculation_type',
        'quantity',
        'unit_amount',
        'amount',
        'unit',
        'note',
        'source_type',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
