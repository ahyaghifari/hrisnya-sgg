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
 *
 * Oncall BUKAN Extra Shift (revisi 2026-09-14, lihat
 * planning/2026-09-14-oncall-middle-redesign.md) - desain awal 2026-09-13 sempat menjadikan
 * Oncall cuma bisa didapat lewat sini (approved menimpa schedule_shift_assignments via
 * syncOncallRoster()/revertOncallRoster(), sudah DIHAPUS). Ternyata di lapangan Oncall itu
 * shift roster BIASA, assignable langsung ke jadwal/rotasi sama seperti Pagi/Siang/Malam/
 * Middle - bedanya cuma cara hitungnya (fleksibel, lihat ShiftScheduleCalculator::
 * calculateOncall()), bukan cara dapatnya. `ExtraShiftRulePage` sudah tidak menawarkan Oncall
 * sebagai `to_shift_type` lagi, jadi baris ExtraShiftAssignment baru seharusnya tidak akan
 * pernah shift_type=oncall lagi.
 *
 * `category` (2026-09-13): 'extra_shift' (default) = penugasan sukarela, KENA jasa dari
 * ExtraShiftRule.amount. 'double_shift' = akibat tukar shift yang conflict (lihat
 * ShiftSwapRequestService::applySlotResolution() mode 'double_shift') - BUKAN penugasan
 * sukarela, TIDAK ada jasa. Payroll (belum diwire) harus skip baris category='double_shift'.
 * `origin_shift_swap_request_id` - null buat extra_shift biasa, terisi buat double_shift biar
 * bisa ditelusuri balik ke pengajuan tukar shift asalnya.
 */
class ExtraShiftAssignment extends Model
{
    use HasUlids;

    public const CATEGORY_EXTRA_SHIFT = 'extra_shift';

    public const CATEGORY_DOUBLE_SHIFT = 'double_shift';

    protected $table = 'extra_shift_assignments';

    protected $fillable = [
        'company_id',
        'user_id',
        'covering_user_id',
        'shift_type_id',
        'date',
        'status',
        'source',
        'category',
        'origin_shift_swap_request_id',
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

    public function originShiftSwapRequest(): BelongsTo
    {
        return $this->belongsTo(ShiftSwapRequests::class, 'origin_shift_swap_request_id');
    }
}
