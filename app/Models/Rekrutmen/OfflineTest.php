<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tes offline (mis. tes tulis di lokasi) — berlaku hanya untuk lowongan yang meng-assign-nya
 * lewat pivot job_opening_offline_test. Beda dari Quiz: tidak ada soal/pengerjaan di sistem,
 * hasilnya dicatat manual (konteks/nama tes sudah ada di sini, nilai & lulus/tidak per
 * kandidat ada di OfflineTestResult).
 */
class OfflineTest extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['name', 'description'];

    public function jobOpenings(): BelongsToMany
    {
        return $this->belongsToMany(JobOpening::class, 'job_opening_offline_test');
    }

    public function results(): HasMany
    {
        return $this->hasMany(OfflineTestResult::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(OfflineTestSchedule::class);
    }
}
