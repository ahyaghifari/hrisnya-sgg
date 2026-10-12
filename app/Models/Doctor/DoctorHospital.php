<?php

namespace App\Models\Doctor;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Penempatan dokter di RS. rumah_sakit_id = m_companies.id (DB HRIS, lintas DB tanpa FK). */
class DoctorHospital extends Model
{
    protected $connection = 'doctor';

    protected $guarded = [];

    protected $casts = ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date'];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function sips(): HasMany
    {
        return $this->hasMany(DoctorLicense::class)->where('jenis', 'SIP');
    }

    /** Lintas DB: dicari manual, bukan relasi Eloquent. */
    public function rumahSakit(): ?Companies
    {
        return Companies::find($this->rumah_sakit_id);
    }
}
