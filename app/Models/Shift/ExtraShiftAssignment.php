<?php

namespace App\Models\Shift;

use App\Enum\ShiftType;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

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
 * KHUSUS shift ONCALL (2026-09-13, instruksi user): beda dari extra shift jenis lain (yang
 * SELALU additive di atas shift normal yang sudah ada), Oncall cuma boleh diajukan di hari
 * LIBUR (lihat ResolvesExtraShiftOptions::resolveNormalShift() - basis 'libur'), jadi tidak ada
 * konflik unique(user_id, date) - baris schedule_shift_assignments hari itu kosong/off_day.
 * Makanya Oncall approved JUGA langsung nimpa schedule_shift_assignments hari itu (lihat
 * syncOncallRoster()) supaya presensinya lewat jalur kalkulator NORMAL (ShiftScheduleCalculator
 * via bucket 'shift' di AccessLogScheduleReader), bukan bucket 'extra_shift' terpisah -
 * nyederhanain: gak perlu APAPUN diubah di AccessLogScheduleReader/SyncAccessLogsAttendance
 * selain nge-filter Oncall dari query ExtraShiftAssignment di sana (biar tidak dobel-proses).
 * Extra shift jenis lain TIDAK disentuh sama sekali, tetap additive seperti semula.
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

    protected static function booted(): void
    {
        static::created(fn (self $assignment) => $assignment->syncOncallRoster());
        static::deleting(fn (self $assignment) => $assignment->revertOncallRoster());
    }

    /** Timpa schedule_shift_assignments hari itu jadi shift Oncall ini - no-op kalau shift_type-nya
     * bukan Oncall. */
    protected function syncOncallRoster(): void
    {
        $this->loadMissing('shiftType');

        if ($this->shiftType?->shift_type !== ShiftType::ONCALL->value) {
            return;
        }

        $existing = ScheduleShiftAssignment::where('user_id', $this->user_id)->whereDate('date', $this->date)->first();

        ScheduleShiftRosterLog::create([
            'schedule_id' => $this->shiftType->schedule_id,
            'user_id' => $this->user_id,
            'date' => $this->date,
            'old_value' => ($existing && ! $existing->off_day) ? $existing->shift_type_id : null,
            'new_value' => $this->shift_type_id,
            'source' => 'extra_shift_oncall',
            'changed_by' => $this->created_by,
        ]);

        // updateOrCreate() TIDAK dipakai di sini - "find"-nya cocokkan attribute mentah, bukan
        // whereDate(), jadi bisa gagal cocok sama baris $existing di atas (kolom `date` di-cast
        // Carbon, representasi string bisa beda) lalu nabrak unique(user_id,date) pas re-insert.
        $attributes = [
            'company_id' => $this->company_id,
            'shift_type_id' => $this->shift_type_id,
            'off_day' => false,
            'created_by' => $this->created_by,
        ];

        if ($existing) {
            $existing->update($attributes);
        } else {
            ScheduleShiftAssignment::create($attributes + ['user_id' => $this->user_id, 'date' => $this->date]);
        }
    }

    /** Kebalikan syncOncallRoster() - dipanggil pas Oncall dibatalkan/dihapus. Baris roster
     * dihapus (bukan di-set off_day=true) - "tidak ada baris" = Libur juga buat
     * resolveNormalShift(), lebih simpel daripada nyimpen state "sebelum Oncall apa" di kolom
     * baru (Oncall cuma boleh diajukan pas hari itu memang libur, jadi aman). */
    protected function revertOncallRoster(): void
    {
        $this->loadMissing('shiftType');

        if ($this->shiftType?->shift_type !== ShiftType::ONCALL->value) {
            return;
        }

        ScheduleShiftRosterLog::create([
            'schedule_id' => $this->shiftType->schedule_id,
            'user_id' => $this->user_id,
            'date' => $this->date,
            'old_value' => $this->shift_type_id,
            'new_value' => null,
            'source' => 'extra_shift_oncall_cancel',
            'changed_by' => Auth::id(),
        ]);

        ScheduleShiftAssignment::where('user_id', $this->user_id)->whereDate('date', $this->date)->delete();
    }

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
