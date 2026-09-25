<?php

namespace App\Models\Shift;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roster harian tipe SHIFT — pengganti `ShiftScheduleAssignments`. Data existing sudah
 * dipindah ke sini (ID dipertahankan sama, lihat migration
 * 2026_07_18_100900_migrate_legacy_shift_data_to_schedules.php), tapi kode yang MENULIS/BACA
 * tabel ini (Filament ScheduleShiftsTable, LemburCalculator, dst) MASIH pakai
 * ShiftScheduleAssignments/shift_schedule_assignments — cutover ke model ini ditunda ke
 * Tahap 2 (lihat planning §5).
 */
class ScheduleShiftAssignment extends Model
{
    use HasUlids;
    use BelongsToCompany;

    protected $table = 'schedule_shift_assignments';

    protected $fillable = [
        'company_id',
        'user_id',
        'shift_type_id',
        'off_day',
        'date',
        'notes',
        'schedule_status',
        'created_by',
        'source_request_id',
    ];

    protected $casts = [
        'date' => 'date',
        'off_day' => 'boolean',
    ];

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ScheduleShiftType::class, 'shift_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
