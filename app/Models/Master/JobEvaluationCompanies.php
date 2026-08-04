<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class JobEvaluationCompanies extends Model
{
    use HasUlids;

    protected $table = 'job_evaluation_companies';

    protected $fillable = [
        'company_id',
        'month',
        'year',
        'score',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
