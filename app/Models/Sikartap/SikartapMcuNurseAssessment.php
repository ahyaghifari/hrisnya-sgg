<?php

namespace App\Models\Sikartap;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SikartapMcuNurseAssessment extends Model
{
    use HasUlids;

    protected $table = 'sikartap_mcu_nurse_assessments';

    protected $guarded = ['id', 'sikartap_id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'rpk_hipertensi' => 'boolean',
            'rpk_stroke' => 'boolean',
            'rpk_jantung' => 'boolean',
            'rpk_diabetes' => 'boolean',
            'alergi_makanan' => 'boolean',
            'alergi_obat' => 'boolean',
            'alergi_debu' => 'boolean',
            'kebiasaan_olahraga' => 'boolean',
            'kebiasaan_merokok' => 'boolean',
            'kebiasaan_alkohol' => 'boolean',
            'kebiasaan_obat' => 'boolean',
        ];
    }

    public function sikartap(): BelongsTo
    {
        return $this->belongsTo(Sikartap::class);
    }
}
