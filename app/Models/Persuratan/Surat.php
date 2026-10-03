<?php

namespace App\Models\Persuratan;

use App\Enum\JenisSurat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Surat extends Model
{
    protected $connection = 'persuratan';

    protected $table = 'surat';

    protected $fillable = [
        'legacy_id', 'legacy_user_id', 'nomor', 'tipe_surat', 'status', 'nomor_urut', 'jenis', 'unit_id', 'tanggal',
        'judul_surat', 'perihal', 'deskripsi',
        'dibuat_oleh_id', 'dibuat_oleh_nama',
    ];

    protected $casts = ['tanggal' => 'date', 'jenis' => JenisSurat::class];

    /** Pembuat dari users hasil impor (legacy_user_id = users.id; tanpa FK — tipe kolom beda). */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'legacy_user_id');
    }

    /** Nama pembuat: dari users kalau ada, cadangan snapshot nama (surat baru). */
    public function getNamaPembuatAttribute(): ?string
    {
        return $this->pembuat?->nama_lengkap ?? $this->dibuat_oleh_nama;
    }

    /** Perusahaan lewat unit: surat -> unit -> perusahaan (tidak disimpan di surat). */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
