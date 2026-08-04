<?php

namespace App\Models\LMS;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsCourseBaselines extends Model
{
    use HasUlids, SoftDeletes;
    use BelongsToCompany;

    protected $table = 'lms_course_baselines';

    protected $fillable = [
        'company_id',
        'start_date',
        'end_date',
        'min_courses',
        'min_hours',
        'is_active',
        'created_by',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
