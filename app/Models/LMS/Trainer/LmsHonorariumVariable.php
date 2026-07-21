<?php

namespace App\Models\LMS\Trainer;

use App\Enum\LMS\TrainingCategoryEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
