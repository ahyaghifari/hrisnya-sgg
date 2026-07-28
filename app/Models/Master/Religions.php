<?php

namespace App\Models\Master;

use App\Helpers\PermissionHelper;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperReligions
 * @property string $id
 * @property string $name
 * @property string|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Master\Companies|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Religions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Religions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Religions query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Religions whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Religions whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Religions whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Religions whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Religions whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Religions extends Model
{
    use HasUlids;

    protected $table = "m_religions";

    protected $fillable = [
        'name',
        'company_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }

            if (Auth::check() && !PermissionHelper::hasGlobalRole(Auth::user())) {
                $model->company_id = Auth::user()->company_id;
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && !PermissionHelper::hasGlobalRole(Auth::user())) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
