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
 *
 * @mixin IdeHelperFeeSubmission
 * @property string $id
 * @property string $company_id
 * @property string|null $reference_number
 * @property string|null $subject_name
 * @property string|null $service_type
 * @property string|null $status
 * @property string|null $notes
 * @property string|null $created_by
 * @property string|null $date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies $company
 * @property-read User|null $createdBy
 * @property-read float $total_amount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payroll\FeeItem> $items
 * @property-read int|null $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereServiceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereSubjectName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeSubmission whereUpdatedAt($value)
 * @mixin \Eloquent
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

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->items()->sum('amount');
    }
}
