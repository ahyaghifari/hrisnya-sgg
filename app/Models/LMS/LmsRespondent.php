<?php

namespace App\Models\LMS;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsRespondent extends Model
{
    use HasUlids;

    /**
     * Respondent dipisah per kategori (bukan 1 baris dipakai bareng kuis+umpan balik) supaya
     * unique(course_id, user_id, category) tidak nabrak begitu jalur publik yang NIP-nya
     * kebetulan cocok ke user yang sama submit kuis DAN umpan balik dalam course yang sama.
     */
    const CATEGORY_KUIS = 'kuis';
    const CATEGORY_UMPAN_BALIK = 'umpan_balik';

    protected $table = 'lms_respondents';

    protected $fillable = [
        'course_id',
        'user_id',
        'name',
        'nip',
        'company_id',
        'unit',
        'category',
    ];

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
