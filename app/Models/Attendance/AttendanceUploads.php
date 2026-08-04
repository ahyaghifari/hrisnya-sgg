<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
