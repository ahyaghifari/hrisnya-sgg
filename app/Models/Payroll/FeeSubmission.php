<?php

namespace App\Models\Payroll;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Generalisasi dari DoctorFeeSubmission — klaim fee per kejadian, bukan cuma buat dokter.
 */
class FeeSubmission extends Model
{
    use HasUlids;

    protected $table = 'fee_submissions';

    protected $fillable = [
        'company_id',
        'reference_number',
        'subject_name',
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
        return $this->hasMany(FeeItem::class, 'submission_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** `amount` dienkripsi (PayrollEncrypted) - SUM SQL gak bisa jalan, jumlah di PHP. */
    public function getTotalAmountAttribute(): float
    {
        return (float) $this->items()->get(['amount'])->sum('amount');
    }
}
