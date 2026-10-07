<?php

namespace App\Models\Doctor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Dokumen dokter. File terenkripsi di disk 'local' (storage/app/private), dibuka lewat route berizin. */
class DoctorDocument extends Model
{
    use SoftDeletes;

    protected $connection = 'doctor';

    protected $guarded = [];

    protected $casts = ['issued_at' => 'date', 'expired_at' => 'date'];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** Status aktif tapi lewat masa berlaku dihitung expired saat tampil - kolom status baru diubah job Fase 4. */
    public function getStatusEfektifAttribute(): string
    {
        return $this->status === 'aktif' && $this->expired_at?->isPast() && ! $this->expired_at->isToday()
            ? 'expired'
            : $this->status;
    }
}
