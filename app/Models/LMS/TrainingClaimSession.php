<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TrainingClaimSession extends Model
{
    use HasUlids;

    protected $fillable = ['training_claim_id', 'date', 'start_time', 'end_time'];

    protected $casts = ['date' => 'date'];
}
