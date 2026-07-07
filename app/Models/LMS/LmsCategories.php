<?php

namespace App\Models\LMS;

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
    ];

    public function courses()
    {
        return $this->hasMany(LmsCourses::class, 'category_id', 'id');
    }
}
