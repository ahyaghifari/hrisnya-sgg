<?php

namespace App\Models\Sp;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Poin pelanggaran dalam 1 surat SP (N per SP, instruksi user 2026-09-25). */
class SpItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'sp_id',
        'text',
        'reason_id',
        'note',
        'repeat_sp_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        $guard = function (SpItem $item) {
            if ($item->sp?->isFixed()) {
                throw new \LogicException('Surat Peringatan sudah dikunci (FIX), isinya tidak bisa diubah.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(SpReason::class, 'reason_id');
    }

    public function sp(): BelongsTo
    {
        return $this->belongsTo(EmployeeSp::class, 'sp_id');
    }
}
