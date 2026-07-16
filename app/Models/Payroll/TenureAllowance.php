<?php

namespace App\Models\Payroll;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bracket Tunjangan Masa Kerja (tahun kerja -> nominal, per company).
 *
 * @mixin IdeHelperTenureAllowance
 */
class TenureAllowance extends Model
{
    use HasUlids;

    protected $table = 'tenure_allowances';

    protected $fillable = [
        'company_id',
        'min_years',
        'max_years',
        'amount',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
