<?php

namespace App\Models\Shift;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperExtraHourAllowanceRules
 */
class ExtraHourAllowanceRules extends Model
{
    use HasUlids, SoftDeletes;
    use BelongsToCompany;

    protected $table = 'extra_hour_rules';

    protected $fillable = [
        'company_id',
        'allowanceable_type',
        'allowanceable_id',
        'amount_per_hour',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function allowanceable(): MorphTo
    {
        return $this->morphTo();
    }
}
