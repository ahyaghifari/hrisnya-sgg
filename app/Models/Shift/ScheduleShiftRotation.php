<?php

namespace App\Models\Shift;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pola rotasi shift per karyawan — 1 baris = 1 posisi hari (`day`, 1-based) dalam siklus
 * rotasi 1 user di 1 Schedule. TIDAK ada entity "Template" terpisah (nama/cycle_length tidak
 * disimpan) — cycle_length dihitung dari COUNT baris `day` milik user itu (lihat dayNumberFor()).
 * Tanggal mulai (anchor) SENGAJA tidak disimpan di sini — tabel ini murni pola, tanggal
 * mulainya ditentukan user tiap kali generate 1 periode (lihat ScheduleShiftRosterTab), bukan
 * bagian dari template.
 */
class ScheduleShiftRotation extends Model
{
    use HasUlids;

    protected $table = 'schedule_shift_rotations';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'shift_type_id',
        'off_day',
        'day',
        'created_by',
    ];

    protected $casts = [
        'off_day' => 'boolean',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ScheduleShiftType::class, 'shift_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Posisi hari-ke-berapa di siklus rotasi 1 user untuk 1 tanggal, dihitung dari $anchorDate
     * yang dipilih user pas generate (bukan disimpan permanen) — null kalau user belum punya
     * baris rotasi sama sekali. $startDay = posisi siklus yang dianggap berlaku PAS di
     * $anchorDate (default 1, tapi user boleh pilih rotasi mulai dari hari ke-berapa di
     * template, bukan selalu dari hari ke-1 — lihat ScheduleShiftRosterTab). */
    public static function dayNumberFor(string $scheduleId, string $userId, Carbon $anchorDate, Carbon $date, int $startDay = 1): ?int
    {
        $cycleLength = static::where('schedule_id', $scheduleId)->where('user_id', $userId)->count();

        if ($cycleLength === 0) {
            return null;
        }

        $diff = $anchorDate->diffInDays($date, false) + ($startDay - 1);

        return (($diff % $cycleLength) + $cycleLength) % $cycleLength + 1;
    }
}
