<?php

namespace App\Models\Doctor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** SIP / STR / STR-KT dokter. File scan opsional lewat doctor_document_id. */
class DoctorLicense extends Model
{
    use SoftDeletes;

    /** Mulai "akan expired" kalau berakhir dalam N hari (selaras reminder terjauh Fase 4: 90 hari). */
    public const EXPIRING_DAYS = 90;

    protected $connection = 'doctor';

    protected $guarded = [];

    protected $casts = ['tanggal_terbit' => 'date', 'tanggal_berlaku' => 'date', 'tanggal_berakhir' => 'date'];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DoctorDocument::class, 'doctor_document_id');
    }

    /**
     * Status yang ditampilkan: nonaktif tetap nonaktif; selain itu dihitung dari tanggal_berakhir
     * (lewat = expired, <= 90 hari = akan_expired). Kolom `status` baru ditulis job Fase 4.
     * Berakhir hari ini masih berlaku (sampai akhir hari).
     */
    public function getStatusEfektifAttribute(): string
    {
        if ($this->status === 'nonaktif' || ! $this->tanggal_berakhir) {
            return $this->status;
        }

        return self::expiryStatus($this->tanggal_berakhir);
    }

    /** aktif / akan_expired (<= 90 hari) / expired untuk satu tanggal berakhir - dipakai juga SK SPK credentialing. */
    public static function expiryStatus(\DateTimeInterface $berakhir): string
    {
        $today = now()->startOfDay();
        $berakhir = \Illuminate\Support\Carbon::instance($berakhir);

        return match (true) {
            $berakhir->lt($today) => 'expired',
            $berakhir->lte($today->copy()->addDays(self::EXPIRING_DAYS)) => 'akan_expired',
            default => 'aktif',
        };
    }
}
