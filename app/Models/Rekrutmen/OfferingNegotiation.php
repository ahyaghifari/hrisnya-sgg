<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferingNegotiation extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    public $timestamps = false;

    protected $fillable = ['job_application_id', 'sender', 'message'];

    protected $casts = ['created_at' => 'datetime'];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}
