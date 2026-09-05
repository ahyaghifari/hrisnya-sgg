<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateTraining extends Model
{
    protected $connection = 'recruitment';

    protected $fillable = ['candidate_id', 'type', 'institution', 'material', 'duration', 'location'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
