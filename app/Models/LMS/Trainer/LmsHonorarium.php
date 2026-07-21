<?php

namespace App\Models\LMS\Trainer;

use App\Enum\LMS\TrainingCategoryEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
