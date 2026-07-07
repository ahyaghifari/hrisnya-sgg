<?php

namespace App\Models\Counseling;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCounseling
 */
class Counseling extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'company_id',
        'issue',
        'response',
        'solution',
        'status',
        'responded_at',
        'responded_by',
    ];



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
