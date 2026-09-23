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
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function sp(): BelongsTo
    {
        return $this->belongsTo(EmployeeSp::class, 'sp_id');
    }
}
