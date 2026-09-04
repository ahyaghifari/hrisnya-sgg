<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McuRadiologyResult extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = [
        'mcu_invitee_id',
        'jenis_pemeriksaan', 'foto_thorax', 'jantung', 'paru_kanan', 'paru_kiri',
        'diafragma', 'tulang', 'kesimpulan', 'file_path',
    ];

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(McuInvitee::class, 'mcu_invitee_id');
    }
}
