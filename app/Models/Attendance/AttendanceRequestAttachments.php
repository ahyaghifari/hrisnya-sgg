<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceRequestAttachments
 */
class AttendanceRequestAttachments extends Model
{
    use HasUlids;

    protected $table = 'attendance_req_attachments';

    protected $fillable = [
        'request_id',
        'file_path',
    ];
}
