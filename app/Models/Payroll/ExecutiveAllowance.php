<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roster pendapatan/potongan manual khusus untuk User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS - lihat
 * migration create_executive_allowances_table & ExecutiveAllowanceCalculator.
 */
class ExecutiveAllowance extends Model
{
    use HasUlids;

    protected $table = 'executive_allowances';

    protected $fillable = [
        'user_id',
        'type',
        'label',
        'amount',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
