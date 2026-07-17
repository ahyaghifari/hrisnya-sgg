<?php

namespace App\Models\Payroll;

use App\Models\Master\Pangkats;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPangkatLemburRate
 */
class PangkatLemburRate extends Model
{
    use HasUlids;

    protected $table = 'pangkat_lembur_rates';

    protected $fillable = [
        'pangkat_id',
        'amount_per_hour',
    ];

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(Pangkats::class, 'pangkat_id');
    }
}
