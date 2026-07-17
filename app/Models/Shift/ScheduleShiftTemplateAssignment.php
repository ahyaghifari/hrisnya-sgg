<?php

namespace App\Models\Shift;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keanggotaan rotasi PERMANEN per user — `anchor_date` tidak berubah walau digenerate
 * berkali-kali per periode, supaya rotasi nyambung lintas bulan tanpa reset. Genuinely baru,
 * lihat planning/2026-07-18-restrukturisasi-jadwal-schedules.md §2.10. Job/action generate
 * bulanan yang MEMAKAI ini belum dibangun (Tahap 2) — model ini baru menyediakan data +
 * helper hitung posisi siklus.
 */
class ScheduleShiftTemplateAssignment extends Model
{
    use HasUlids;

    protected $table = 'schedule_shift_template_assignments';

    protected $fillable = [
        'user_id',
        'shift_template_id',
        'anchor_date',
        'effective_until',
        'created_by',
    ];

    protected $casts = [
        'anchor_date' => 'date',
        'effective_until' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleShiftTemplate::class, 'shift_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Posisi hari-ke-berapa di siklus template untuk 1 tanggal, dihitung dari anchor_date
     * (permanen) — BUKAN dari tanggal generate. Lihat planning §2.10 poin 3.
     */
    public function dayNumberFor(Carbon $date): int
    {
        $cycleLength = $this->shiftTemplate->cycle_length;
        $diffDays = $this->anchor_date->diffInDays($date, false);

        return (int) (($diffDays % $cycleLength + $cycleLength) % $cycleLength) + 1;
    }
}
