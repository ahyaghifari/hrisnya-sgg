<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsMeetingAttendances
 * @property string $id
 * @property string $meeting_id
 * @property string $user_id
 * @property string $status
 * @property string|null $checked_in_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsMeetings $meeting
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances whereCheckedInAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingAttendances whereUserId($value)
 * @mixin \Eloquent
 */
class LmsMeetingAttendances extends Model
{
    use HasUlids;

    protected $table = 'lms_meeting_attendances';

    protected $fillable = [
        'meeting_id',
        'user_id',
        'status',
        'checked_in_at',
    ];

    public function meeting()
    {
        return $this->belongsTo(LmsMeetings::class, 'meeting_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
