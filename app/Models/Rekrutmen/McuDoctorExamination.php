<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McuDoctorExamination extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = [
        'mcu_invitee_id',
        'kulit', 'rambut', 'visus_mata', 'buta_warna', 'lapang_pandang',
        'hidung', 'telinga', 'tonsil', 'faring', 'gigi', 'kelenjar_gondok',
        'suara_pernafasan', 'ronchi', 'wheezing', 'lainnya_paru', 'jantung',
        'hati', 'limpa', 'epigastrium', 'ginjal', 'lainnya_abdomen',
        'ekstremitas', 'neurologi', 'diagnosa', 'rekomendasi', 'catatan', 'file_path', 'rekomendasi_fit',
    ];

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(McuInvitee::class, 'mcu_invitee_id');
    }
}
