<?php

namespace App\Models\Master\Attendance;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /** User yang di-assign khusus ke device ini walau company_id-nya beda - lihat migration
     * attendance_device_users. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'attendance_device_users', 'attendance_device_id', 'user_id');
    }
}
