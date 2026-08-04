<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generalisasi dari DoctorFeeItem — `is_manual` dipakai karena banyak item input manual
 * ad-hoc yang tidak link ke katalog komponen (lihat
 * knowledge/markdown/analisis-payroll-2-redesign-struktur.md §2). Kolom `salary_component_id`
 * (dulu link ke katalog lama) dihapus di Fase 1 — katalog freeform sudah diganti
 * PayrollComponentEnum tertutup, tidak ada lagi "link ke baris katalog" buat FeeItem.
 */
class FeeItem extends Model
{
    use HasUlids;

    protected $table = 'fee_items';

    protected $fillable = [
        'submission_id',
        'user_id',
        'is_manual',
        'name',
        'amount',
        'is_paid',
        'notes',
    ];

    protected $casts = [
        'is_manual' => 'boolean',
        'is_paid' => 'boolean',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeeSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
