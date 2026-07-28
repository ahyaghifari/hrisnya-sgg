<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAssessmentRatingScales
 * @property int $id
 * @property string $type_id
 * @property string $company_id
 * @property numeric $score_min
 * @property numeric $score_max
 * @property string $label
 * @property string|null $description
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereScoreMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereScoreMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentRatingScales whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AssessmentRatingScales extends Model
{
    //
}
