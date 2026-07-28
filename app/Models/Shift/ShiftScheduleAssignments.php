<?php

namespace App\Models\Shift;

use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperShiftScheduleAssignments
 * @property string $id
 * @property string $company_id
 * @property string $user_id
 * @property string $shift_id
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $notes
 * @property string|null $schedule_status
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Master\Companies $company
 * @property-read User|null $creator
 * @property-read \App\Models\Shift\MasterShift|null $shift
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereScheduleStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftScheduleAssignments whereUserId($value)
 * @mixin \Eloquent
 */
class ShiftScheduleAssignments extends Model
{
    use HasUlids;
    use BelongsToCompany;

    protected $table = 'shift_schedule_assignments';

    protected $fillable = [
        'company_id',
        'user_id',
        'shift_id',
        'date',
        'notes',
        'schedule_status',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function shift()
    {
        return $this->belongsTo(MasterShift::class, 'shift_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Master\Companies::class, 'company_id');
    }
}
