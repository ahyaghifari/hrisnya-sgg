<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $variable_id
 * @property string $name
 * @property int $score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\Trainer\LmsHonorariumVariable $variable
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumCategory whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumCategory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsHonorariumCategory whereVariableId($value)
 * @mixin \Eloquent
 */
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
