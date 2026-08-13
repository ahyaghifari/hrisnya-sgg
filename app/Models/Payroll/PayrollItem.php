<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $casts = [
        'unit_amount' => PayrollEncrypted::class,
        'amount' => PayrollEncrypted::class,
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
