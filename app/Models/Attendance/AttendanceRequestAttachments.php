<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperAttendanceRequestAttachments
 * @property string $id
 * @property string $request_id
 * @property string $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $file_url
 * @property-read bool $is_image
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestAttachments newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestAttachments newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestAttachments query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestAttachments whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestAttachments whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestAttachments whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestAttachments whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestAttachments whereUpdatedAt($value)
 * @mixin \Eloquent
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
