<?php

namespace App\Models\Master\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read Companies|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceDevice query()
 * @mixin \Eloquent
 */
class AttendanceDevice extends Model
{
    use HasUlids;

    protected $table = 'attendance_devices';

    protected $fillable = [
        'company_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
