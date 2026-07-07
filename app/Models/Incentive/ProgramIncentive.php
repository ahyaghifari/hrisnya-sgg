<?php

namespace App\Models\Incentive;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperProgramIncentive
 */
class ProgramIncentive extends Model
{
    use HasUlids;
    use BelongsToCompany;

    protected $table = 'program_incentives';
    protected $fillable = [
        'company_id',
        'user_id',
        'program_name',
        'incentive_date',
        'amount',
        'note',
        'is_processed',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
