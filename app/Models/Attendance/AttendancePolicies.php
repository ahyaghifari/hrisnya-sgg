<?php

namespace App\Models\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAttendancePolicies
 * @property string $id
 * @property string|null $company_id
 * @property string $name
 * @property string $type
 * @property int $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendancePolicies whereValue($value)
 * @mixin \Eloquent
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
