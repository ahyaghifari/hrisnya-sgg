<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTrainerScoreCategories
 */
class TrainerScoreCategories extends Model
{
    use HasUlids;

    protected $table = 'trainer_score_categories';

    protected $fillable = [
        'variable_id',
        'name',
        'score',
    ];

    public function variable()
    {
        return $this->belongsTo(TrainerScoreVariables::class, 'variable_id');
    }
}
