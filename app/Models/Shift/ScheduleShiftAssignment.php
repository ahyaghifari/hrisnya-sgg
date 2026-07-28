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
 *
 * @property string $id
 * @property string $company_id
 * @property string $user_id
 * @property string|null $shift_type_id
 * @property bool $off_day
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $notes
 * @property string|null $schedule_status
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies $company
 * @property-read User|null $creator
 * @property-read \App\Models\Shift\ScheduleShiftType|null $shiftType
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereOffDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereScheduleStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereShiftTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftAssignment whereUserId($value)
 * @mixin \Eloquent
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
