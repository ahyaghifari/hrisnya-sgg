<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jabatan Direktur 1 perusahaan (1 baris per company). Resolusi & tulis lewat
 * App\Services\CompanyDirectorService — jangan query model ini langsung dari fitur.
 */
class CompanyDirector extends Model
{
    protected $fillable = ['company_id', 'job_position_id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }
}
