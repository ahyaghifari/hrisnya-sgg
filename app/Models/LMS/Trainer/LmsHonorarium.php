<?php

namespace App\Models\LMS\Trainer;

use App\Enum\LMS\TrainingCategoryEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $context
 * @property int $min_score
 * @property int $max_score
 * @property float $honor_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium whereContext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium whereHonorAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium whereMaxScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium whereMinScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorarium whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LmsHonorarium extends Model
{
    use HasUlids;

    protected $table = 'lms_honorarium';

    protected $fillable = [
        'context',
        'min_score',
        'max_score',
        'honor_amount',
    ];

    public function contextEnum(): ?TrainingCategoryEnum
    {
        return TrainingCategoryEnum::tryFrom($this->context);
    }
}
