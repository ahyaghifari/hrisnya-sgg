<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineTestInvitee extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['offline_test_schedule_id', 'job_application_id'];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(OfflineTestSchedule::class, 'offline_test_schedule_id');
    }
}
