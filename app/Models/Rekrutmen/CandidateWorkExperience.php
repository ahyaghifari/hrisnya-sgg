<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateWorkExperience extends Model
{
    protected $connection = 'recruitment';

    protected $fillable = [
        'candidate_id', 'company_name', 'business_type', 'company_address',
        'position', 'start_date', 'end_date', 'salary', 'resign_reason', 'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'salary' => 'decimal:2',
        'is_current' => 'boolean',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
