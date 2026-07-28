<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAssessmentTypes
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTypes withoutTrashed()
 * @mixin \Eloquent
 */
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
}
