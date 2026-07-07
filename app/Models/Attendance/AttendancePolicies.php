<?php

namespace App\Models\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAttendancePolicies
 */
class AttendancePolicies extends Model
{
    use HasUlids;

    protected $table = 'attendance_policies';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'value',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
