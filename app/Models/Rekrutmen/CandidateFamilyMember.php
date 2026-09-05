<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateFamilyMember extends Model
{
    protected $connection = 'recruitment';

    protected $fillable = ['candidate_id', 'relation', 'gender', 'name', 'birth_date', 'education', 'job', 'phone'];

    protected $casts = ['birth_date' => 'date'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
