<?php

namespace App\Models\Master\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperHolidays
 */
class Holidays extends Model
{
    use HasUlids;

    protected $table = 'm_holidays';

    protected $fillable = [
        'company_id',
        'date',
        'name',
        'is_collective_leave',
    ];

    protected $casts = [
        'date' => 'date',
        'is_collective_leave' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
