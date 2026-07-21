<?php

namespace App\Models\LMS;

use App\Enum\LMS\TrainingCategoryEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCategories
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
