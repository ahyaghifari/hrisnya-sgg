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
        'draft_request_id',
        'draft_shift_type_id',
        'draft_off_day',
        'draft_reason',
    ];

    protected $casts = [
        // 'date:Y-m-d' (bukan 'date' polos) - cast 'date' Eloquent default nulis 'Y-m-d H:i:s'
        // saat SAVE lewat model (create()/save() biasa, beda dari upsert() mentah yang dipakai
        // RosterBatchWriter/ScheduleRosterChangeService) - di MySQL kolom DATE asli otomatis
        // memangkas jam-nya jadi tidak kelihatan, tapi di SQLite (tanpa tipe DATE asli, disimpan
        // TEXT apa adanya) itu BOCOR, bikin whereBetween('date', [...]) berbasis string gagal
        // cocok (ditemukan 2026-09-28 lewat test approval jadwal shift yang flaky).
        'date' => 'date:Y-m-d',
        'off_day' => 'boolean',
        'draft_off_day' => 'boolean',
    ];

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ScheduleShiftType::class, 'shift_type_id');
    }

    public function draftShiftType(): BelongsTo
    {
        return $this->belongsTo(ScheduleShiftType::class, 'draft_shift_type_id');
    }

    /** Ada pengajuan yang belum disetujui/ditolak untuk sel ini - lihat
     * App\Services\Shift\ScheduleRosterChangeService. */
    public function getHasDraftAttribute(): bool
    {
        return $this->draft_request_id !== null;
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
