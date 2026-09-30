<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewInvitee extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['interview_schedule_id', 'job_application_id'];

    /** Tahap ke berapa undangan ini - ikut jadwalnya. */
    public function round(): int
    {
        return $this->schedule?->round ?: 1;
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(InterviewSchedule::class, 'interview_schedule_id');
    }
}
