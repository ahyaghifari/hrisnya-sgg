<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nilai & keputusan lulus/tidak satu kandidat untuk satu OfflineTest. */
class OfflineTestResult extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = [
        'job_application_id', 'offline_test_id', 'score', 'result', 'notes', 'recorded_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function offlineTest(): BelongsTo
    {
        return $this->belongsTo(OfflineTest::class);
    }
}
