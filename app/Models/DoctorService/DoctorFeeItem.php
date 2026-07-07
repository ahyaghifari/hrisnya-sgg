<?php

namespace App\Models\DoctorService;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperDoctorFeeItem
 */
class DoctorFeeItem extends Model
{
    use HasUlids;

    protected $table = 'doctor_fee_items';

    protected $fillable = [
        'submission_id',
        'user_id',
        'doctor_service_id',
        'service_name',
        'amount',
        'is_paid',
        'notes',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DoctorFeeSubmission::class, 'submission_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(MasterDoctorService::class, 'doctor_service_id');
    }
}
