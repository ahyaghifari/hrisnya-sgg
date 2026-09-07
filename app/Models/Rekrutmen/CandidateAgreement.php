<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateAgreement extends Model
{
    protected $connection = 'recruitment';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'candidate_id', 'title', 'content', 'company_agreement_id', 'job_opening_agreement_id',
        'agreed_at', 'ip_address', 'user_agent',
    ];

    protected $casts = ['agreed_at' => 'datetime'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function companyAgreement(): BelongsTo
    {
        return $this->belongsTo(CompanyAgreement::class);
    }

    public function jobOpeningAgreement(): BelongsTo
    {
        return $this->belongsTo(JobOpeningAgreement::class);
    }
}
