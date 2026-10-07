<?php

namespace App\Models\Doctor;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Satu proses credentialing dokter di satu RS; PDF finalnya = doctor_document_id. Riwayat tidak ditimpa (banyak case per dokter). */
class CredentialingCase extends Model
{
    use SoftDeletes;

    protected $connection = 'doctor';

    protected $guarded = [];

    protected $casts = [
        'tanggal_pengajuan' => 'date', 'tanggal_selesai' => 'date',
        'spk_terbit' => 'date', 'spk_berakhir' => 'date',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DoctorDocument::class, 'doctor_document_id');
    }

    /** Lintas DB: dicari manual, bukan relasi Eloquent. */
    public function rumahSakit(): ?Companies
    {
        return Companies::find($this->rumah_sakit_id);
    }

    /** Status masa berlaku SK SPK (aktif / akan_expired / expired), null kalau belum ada tanggal berakhir. */
    public function getSpkStatusAttribute(): ?string
    {
        return $this->spk_berakhir ? DoctorLicense::expiryStatus($this->spk_berakhir) : null;
    }
}
