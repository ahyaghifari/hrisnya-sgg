<?php

namespace App\Models\Shift;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Extra shift - shift TAMBAHAN penuh di tanggal yang karyawan itu sudah punya shift normal
 * (mis. dijadwalkan pagi, diminta cover shift siang juga karena rekannya libur). Versi pertama
 * cuma jalur `source=assigned` (atasan input langsung, auto status=approved) - jalur
 * `requested` (pengajuan karyawan + approval engine) menyusul di fase berikutnya, lihat
 * planning/2026-07-31-fitur-extra-shift.md.
 *
 * Sengaja tabel terpisah dari schedule_shift_assignments (bukan baris ke-2 di tabel itu) -
 * schedule_shift_assignments punya unique(user_id, date), tidak bisa nampung shift ke-2 di
 * tanggal yang sama.
 */
class ExtraShiftAssignment extends Model
{
    use HasUlids;

    protected $table = 'extra_shift_assignments';

    protected $fillable = [
        'company_id',
        'user_id',
        'covering_user_id',
        'shift_type_id',
        'date',
        'status',
        'source',
        'reason',
        'created_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coveringUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'covering_user_id');
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ScheduleShiftType::class, 'shift_type_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendanceRecord(): HasOne
    {
        return $this->hasOne(ExtraShiftAttendanceRecord::class);
    }
}
