<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Komponen kompensasi offering letter per lowongan — dikelola di panel rekrutmen atau HRIS. */
class JobOpeningOfferingCompensationRow extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['job_opening_id', 'label', 'amount_orientasi', 'amount_kontrak', 'sort_order'];

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }
}
