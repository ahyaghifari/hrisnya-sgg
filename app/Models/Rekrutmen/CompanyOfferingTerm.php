<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris ketentuan umum offering letter — dikelola di panel rekrutmen. */
class CompanyOfferingTerm extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['company_id', 'label', 'sort_order'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
