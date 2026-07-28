<?php

namespace App\Models\LMS;

use App\Enum\LMS\TrainingCategoryEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCategories
 * @property string $id
 * @property string $name
 * @property TrainingCategoryEnum|null $training_category
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsCourses> $courses
 * @property-read int|null $courses_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCategories newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCategories newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCategories query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCategories whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCategories whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCategories whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCategories whereTrainingCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCategories whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LmsCategories extends Model
{
    use HasUlids;

    protected $table = 'lms_categories';

    protected $fillable = [
        'name',
        'training_category',
    ];

    protected function casts(): array
    {
        return [
            'training_category' => TrainingCategoryEnum::class,
        ];
    }

    public function courses()
    {
        return $this->hasMany(LmsCourses::class, 'category_id', 'id');
    }
}
