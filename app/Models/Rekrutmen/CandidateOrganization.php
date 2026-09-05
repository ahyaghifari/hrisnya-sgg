<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateOrganization extends Model
{
    protected $connection = 'recruitment';

    protected $fillable = ['candidate_id', 'organization_name', 'field', 'position', 'duration', 'location'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
