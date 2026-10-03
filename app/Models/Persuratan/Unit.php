<?php

namespace App\Models\Persuratan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $connection = 'persuratan';

    protected $table = 'unit';

    protected $fillable = ['perusahaan_id', 'legacy_id', 'kode', 'nama', 'hris_unit_id'];

    /** Unit yang sudah dipakai surat tidak boleh dihapus (FK juga menolak; ini supaya pesannya jelas & aman dari jalur mana pun). */
    protected static function booted(): void
    {
        static::deleting(fn (Unit $unit) => ! $unit->surat()->exists());
    }

    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(Perusahaan::class);
    }

    public function surat(): HasMany
    {
        return $this->hasMany(Surat::class);
    }
}
