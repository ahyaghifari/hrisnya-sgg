<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsHonorariumCategory extends Model
{
    use HasUlids;

    protected $table = 'lms_honorarium_categories';

    protected $fillable = [
        'variable_id',
        'name',
        'score',
    ];

    public function variable()
    {
        return $this->belongsTo(LmsHonorariumVariable::class, 'variable_id');
    }
}
