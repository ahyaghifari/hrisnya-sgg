<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function getIsImageAttribute(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $this->file_path ?? '');
    }
}
