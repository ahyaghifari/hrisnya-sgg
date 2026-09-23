<?php

namespace App\Models\Sp;

use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpSourceType;
use App\Models\Coaching\Coaching;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeSp extends Model
{
    use HasUlids;

    protected $fillable = [
        'nomor',
        'user_id',
        'company_id',
        'sp_level',
        'source_type',
        'source_id',
        'start_date',
        'end_date',
        'created_by',
        'knows_by',
    ];

    protected $casts = [
        'sp_level' => SpLevel::class,
        'source_type' => SpSourceType::class,
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * SP aktif = source of truth bisnis, selalu live-query (planning/2026-09-23-sp-dan-merit-point.md
     * §2.2) - JANGAN baca dari cache user_hr_balances buat validasi.
     */
    public function scopeActive($query)
    {
        return $query->whereDate('start_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
            });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** "Mengetahui" - pimpinan/HC Supervisor/Manager yang menyetujui surat ini (instruksi user
     * 2026-09-25, sesuai flowchart approval HC-GA). Nullable - approval-nya offline/verbal. */
    public function knowsBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'knows_by');
    }

    /** Poin-poin pelanggaran (instruksi user 2026-09-25) - isi surat, menggantikan reason_id/reason_note lama. */
    public function items(): HasMany
    {
        return $this->hasMany(SpItem::class, 'sp_id')->orderBy('sort_order');
    }

    /** Berita Acara (instruksi user 2026-09-25) - 1:1, catatan investigasi HR-internal. */
    public function report(): HasOne
    {
        return $this->hasOne(SpReport::class, 'sp_id');
    }

    /**
     * Coaching yang jadi sumber SP ini (instruksi user 2026-09-23) - penanda saja bahwa
     * karyawan sudah pernah dicoaching sebelum SP ini terbit, dipilih manual saat SP dibuat
     * (CreateSpPage/EmployeeSpResource), BUKAN pemicu otomatis. Null kalau source_type=MANUAL
     * (source_id null) atau sumbernya bukan Coaching.
     * ponytail: belongsTo polos ke `source_id` tanpa filter source_type di query - aman selama
     * SpSourceType cuma COACHING|MANUAL (source_id selalu null utk MANUAL). Tambahkan filter
     * eksplisit kalau source_id nanti dipakai source_type lain (mis. ATTENDANCE, §5).
     */
    public function coaching(): BelongsTo
    {
        return $this->belongsTo(Coaching::class, 'source_id');
    }
}
