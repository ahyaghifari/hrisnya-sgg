<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperJobEvaluationCompanies
 * @property string $id
 * @property string $company_id
 * @property string $month
 * @property string $year
 * @property int $score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Master\Companies $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies whereMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobEvaluationCompanies whereYear($value)
 * @mixin \Eloquent
 */
class JobEvaluationCompanies extends Model
{
    use HasUlids;

    protected $table = 'job_evaluation_companies';

    protected $fillable = [
        'company_id',
        'month',
        'year',
        'score',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
