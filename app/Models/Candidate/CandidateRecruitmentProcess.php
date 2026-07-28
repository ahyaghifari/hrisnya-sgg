<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCandidateRecruitmentProcess
 * @property string $id
 * @property string $application_id
 * @property string $process_type
 * @property string $process_name
 * @property int $sequence
 * @property string $status
 * @property string|null $link
 * @property int|null $score
 * @property string|null $notes
 * @property array<array-key, mixed>|null $attachments
 * @property string|null $feedback
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\CandidateJobApplications|null $application
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereAttachments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereFeedback($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereProcessName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereProcessType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateRecruitmentProcess whereUpdatedAt($value)
 * @mixin \Eloquent
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
