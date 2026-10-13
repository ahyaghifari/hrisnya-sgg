<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * STR/SIP karyawan nakes. Perpanjangan = baris baru; yang lama is_active=false (riwayat, tetap bisa dibuka).
 * Data tersimpan tidak diedit. berlaku_sampai NULL = seumur hidup (umumnya STR); SIP ~5 tahun.
 * File terenkripsi di disk privat 'local', dilayani route profil-saya.dokumen-nakes.show.
 */
class EmployeeNakesDocument extends Model
{
    use HasUlids;

    protected $table = 'employee_nakes_documents';

    protected $fillable = ['employee_id', 'type', 'nomor', 'file_path', 'berlaku_mulai', 'berlaku_sampai', 'is_active'];

    protected $casts = ['berlaku_mulai' => 'date', 'berlaku_sampai' => 'date', 'is_active' => 'boolean'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function getFileUrlAttribute(): string
    {
        return route('profil-saya.dokumen-nakes.show', $this);
    }

    /** Sisa hari sampai habis (negatif = sudah lewat); null = seumur hidup. */
    public function sisaHari(): ?int
    {
        return $this->berlaku_sampai ? (int) now()->startOfDay()->diffInDays($this->berlaku_sampai, false) : null;
    }
}
