<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsMeetingAttendances
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
