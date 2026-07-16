<?php

namespace App\Models\Payroll;

use App\Models\Master\Pangkats;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPangkatSalary
 */
class PangkatSalary extends Model
{
    use HasUlids;

    protected $table = 'pangkat_salaries';

    protected $fillable = [
        'pangkat_id',
        'base_salary',
    ];

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(Pangkats::class, 'pangkat_id');
    }
}
