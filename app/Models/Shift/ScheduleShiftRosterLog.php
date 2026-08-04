<?php

namespace App\Models\Shift;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan roster harian (schedule_shift_assignments), 1 baris = 1 sel (user_id+date)
 * yang berubah - lihat migration create_schedule_shift_roster_logs_table buat konteks lengkap.
 */
class ScheduleShiftRosterLog extends Model
{
    use HasUlids;

    protected $table = 'schedule_shift_roster_logs';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'date',
        'old_value',
        'new_value',
        'source',
        'changed_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
