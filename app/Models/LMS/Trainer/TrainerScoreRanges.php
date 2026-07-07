<?php

namespace App\Models\LMS\Trainer;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTrainerScoreRanges
 */
class TrainerScoreRanges extends Model
{
    use HasUlids;

    protected $table = 'trainer_score_ranges';

    protected $fillable = [
        'company_id',
        'type',
        'min_score',
        'max_score',
        'honor_amount',
        'validity_months',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
