<?php

namespace App\Models\LMS\Trainer;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTrainerScoreVariables
 */
class TrainerScoreVariables extends Model
{
    use HasUlids;

    protected $table = 'trainer_score_variables';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'type',
    ];

    public function categories()
    {
        return $this->hasMany(TrainerScoreCategories::class, 'variable_id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
