<?php

namespace App\Models\Doctor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Master dokter di DB `doctor` (terpisah dari HRIS). Akun login = users HRIS lewat hris_user_id (tanpa FK). */
class Doctor extends Model
{
    use SoftDeletes;

    protected $connection = 'doctor';

    protected $guarded = [];

    protected $casts = ['tanggal_lahir' => 'date'];

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'doctor_specialties')->withPivot('is_primary')->withTimestamps();
    }

    public function hospitals(): HasMany
    {
        return $this->hasMany(DoctorHospital::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DoctorDocument::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(DoctorLicense::class);
    }

    public function credentialingCases(): HasMany
    {
        return $this->hasMany(CredentialingCase::class);
    }

    /** Lintas DB: tidak bisa relasi Eloquent biasa ke HRIS, jadi cari manual (dokter disembunyikan global scope 'dokter'). */
    public function hrisUser(): ?User
    {
        return $this->hris_user_id ? User::withoutGlobalScopes()->find($this->hris_user_id) : null;
    }

    /** "dr. Nama, Sp.X" - nama disimpan tanpa gelar. */
    public function getNamaLengkapAttribute(): string
    {
        return trim(($this->gelar_depan ? $this->gelar_depan.' ' : '').$this->nama.($this->gelar_belakang ? ', '.$this->gelar_belakang : ''));
    }
}
