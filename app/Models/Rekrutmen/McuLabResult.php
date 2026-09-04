<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McuLabResult extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = [
        'mcu_invitee_id',
        'hemoglobin', 'leukosit', 'eritrosit', 'trombosit', 'hematokrit',
        'basofil', 'eosinofil', 'stab', 'segmen', 'limfosit', 'monosit',
        'mcv', 'mch', 'mchc', 'gds', 'gda',
        'hbsag', 'hiv', 'kesimpulan', 'file_path',
    ];

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(McuInvitee::class, 'mcu_invitee_id');
    }
}
