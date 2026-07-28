<?php

namespace App\Models\LMS\Trainer;

use App\Enum\LMS\TrainingCategoryEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $context
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\Trainer\LmsHonorariumCategory> $categories
 * @property-read int|null $categories_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumVariable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumVariable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumVariable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumVariable whereContext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumVariable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumVariable whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumVariable whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumVariable whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumVariable whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LmsHonorariumVariable extends Model
{
    use HasUlids;

    protected $table = 'lms_honorarium_variables';

    protected $fillable = [
        'context',
        'name',
        'description',
    ];

    public function categories()
    {
        return $this->hasMany(LmsHonorariumCategory::class, 'variable_id');
    }

    public function contextEnum(): ?TrainingCategoryEnum
    {
        return TrainingCategoryEnum::tryFrom($this->context);
    }
}
