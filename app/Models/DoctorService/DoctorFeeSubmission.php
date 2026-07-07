<?php

namespace App\Models\DoctorService;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDoctorFeeSubmission
 */
class DoctorFeeSubmission extends Model
{
    use HasUlids;

    protected $table = 'doctor_fee_submissions';

    protected $fillable = [
        'company_id',
        'sep_number',
        'patient_name',
        'total_claim',
        'service_type',
        'status',
        'notes',
        'created_by',
        'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DoctorFeeItem::class, 'submission_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->items()->sum('amount');
    }
}
