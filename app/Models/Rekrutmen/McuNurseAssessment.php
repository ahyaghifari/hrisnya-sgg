<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McuNurseAssessment extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = [
        'mcu_invitee_id',
        'tinggi_badan', 'berat_badan', 'tekanan_darah', 'nadi', 'suhu', 'pernafasan',
        'keluhan_kesehatan',
        'rpk_hipertensi', 'rpk_stroke', 'rpk_jantung', 'rpk_diabetes', 'rpk_lainnya',
        'riwayat_penyakit_dahulu', 'riwayat_operasi',
        'alergi_makanan', 'alergi_obat', 'alergi_debu', 'alergi_lainnya',
        'kebiasaan_olahraga', 'kebiasaan_merokok', 'kebiasaan_alkohol', 'kebiasaan_obat', 'kebiasaan_lainnya',
        'hasil_ekg', 'keterangan_ekg', 'file_ekg', 'catatan',
    ];

    protected $casts = [
        'rpk_hipertensi' => 'boolean', 'rpk_stroke' => 'boolean', 'rpk_jantung' => 'boolean',
        'rpk_diabetes' => 'boolean', 'alergi_makanan' => 'boolean', 'alergi_obat' => 'boolean',
        'alergi_debu' => 'boolean', 'kebiasaan_olahraga' => 'boolean', 'kebiasaan_merokok' => 'boolean',
        'kebiasaan_alkohol' => 'boolean', 'kebiasaan_obat' => 'boolean',
    ];

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(McuInvitee::class, 'mcu_invitee_id');
    }
}
