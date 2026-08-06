<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentTypes extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'assessment_types';

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $last = self::withTrashed()->orderBy('id', 'desc')->first();
            $nextNumber = $last ? ((int)str_replace('ASSTYPE-', '', $last->code) + 1) : 1;
            $model->code = 'ASSTYPE-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        });
    }

    public function templates()
    {
        return $this->hasMany(AssessmentTemplates::class, 'type_id');
    }
}
