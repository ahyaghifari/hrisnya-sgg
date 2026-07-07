<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCandidateRecruitmentProcess
 */
class CandidateRecruitmentProcess extends Model
{
    use HasUlids;

    protected $table = 'recruitment_processes';

    protected $fillable = [
        'application_id',
        'process_type',
        'process_name',
        'sequence',
        'status',
        'link',
        'score',
        'notes',
        'metadata',
        'attachments',
        'feedback',
    ];

    protected $casts = [
        'metadata' => 'array',
        'attachments' => 'array',
    ];

    public function application()
    {
        return $this->belongsTo(CandidateJobApplications::class, 'application_id');
    }
}
