<?php

namespace App\Models\Shift;

use App\Casts\PayrollEncrypted;
use App\Enum\ShiftType;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aturan transisi extra shift (mis. Pagi->Siang boleh, Malam->Pagi tidak boleh - baris tidak
 * ada di tabel ini = tidak boleh) + nominal insentifnya. Generik per-company (berlaku untuk
 * SEMUA jadwal company itu), beda dari ExtraShiftAssignment.shift_type_id yang merujuk shift
 * SPESIFIK punya jadwal tertentu.
 *
 * Dua permukaan edit, satu tabel: pasangan (from/to) dikelola di Presensi
 * (ExtraShiftRulePage, create+delete saja), nominal (`amount`) diisi belakangan di Payroll
 * (MappingKomponenGajiPage tab Extra Shift, cuma update `amount`, tidak bisa tambah/hapus
 * pasangan).
 */
class ExtraShiftRule extends Model
{
    use HasUlids;

    protected $table = 'extra_shift_rules';

    protected $fillable = [
        'company_id',
        'from_shift_type',
        'to_shift_type',
        'amount',
    ];

    protected $casts = [
        'amount' => PayrollEncrypted::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function getFromShiftTypeLabelAttribute(): string
    {
        return ShiftType::tryFrom($this->from_shift_type)?->getLabel() ?? $this->from_shift_type;
    }

    public function getToShiftTypeLabelAttribute(): string
    {
        return ShiftType::tryFrom($this->to_shift_type)?->getLabel() ?? $this->to_shift_type;
    }
}
