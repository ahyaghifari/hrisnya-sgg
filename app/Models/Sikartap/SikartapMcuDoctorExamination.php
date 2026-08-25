<?php

namespace App\Models\Sikartap;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SikartapMcuDoctorExamination extends Model
{
    use HasUlids;

    protected $table = 'sikartap_mcu_doctor_examinations';

    protected $guarded = ['id', 'sikartap_id', 'created_at', 'updated_at'];

    public function sikartap(): BelongsTo
    {
        return $this->belongsTo(Sikartap::class);
    }
}
