<?php

namespace App\Models\Payroll;

use App\Models\SalaryComponent\SalaryComponents;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generalisasi dari DoctorFeeItem — salary_component_id nullable karena banyak item input
 * manual ad-hoc yang tidak link ke katalog (lihat
 * knowledge/markdown/analisis-payroll-2-redesign-struktur.md §2).
 *
 * @mixin IdeHelperFeeItem
 */
class FeeItem extends Model
{
    use HasUlids;

    protected $table = 'fee_items';

    protected $fillable = [
        'submission_id',
        'user_id',
        'salary_component_id',
        'is_manual',
        'name',
        'amount',
        'is_paid',
        'notes',
    ];

    protected $casts = [
        'is_manual' => 'boolean',
        'is_paid' => 'boolean',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeeSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponents::class, 'salary_component_id');
    }
}
