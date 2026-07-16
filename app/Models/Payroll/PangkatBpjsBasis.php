<?php

namespace App\Models\Payroll;

use App\Models\Master\Pangkats;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPangkatBpjsBasis
 */
class PangkatBpjsBasis extends Model
{
    use HasUlids;

    protected $table = 'pangkat_bpjs_bases';

    protected $fillable = [
        'pangkat_id',
        'bpjs_basis',
    ];

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(Pangkats::class, 'pangkat_id');
    }
}
