<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceUploads
 * @property string $id
 * @property string|null $uploaded_by
 * @property string $file_path
 * @property string|null $original_name
 * @property string $status
 * @property int $record_count
 * @property int $processed_count
 * @property int $error_count
 * @property array<array-key, mixed>|null $error_log
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $uploader
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereErrorCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereErrorLog($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereOriginalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereProcessedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereRecordCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceUploads whereUploadedBy($value)
 * @mixin \Eloquent
 */
class AttendanceUploads extends Model
{
    use HasUlids;

    protected $table = 'attendance_uploads';

    protected $fillable = [
        'uploaded_by',
        'file_path',
        'original_name',
        'status',
        'record_count',
        'processed_count',
        'error_count',
        'error_log',
        'processed_at',
    ];

    protected $casts = [
        'error_log' => 'array',
        'processed_at' => 'datetime',
    ];

    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
