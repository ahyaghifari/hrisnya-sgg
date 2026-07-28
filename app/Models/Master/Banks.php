<?php

namespace App\Models\Master;

use App\Helpers\PermissionHelper;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperBanks
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Master\Companies|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banks newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banks newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banks query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banks whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banks whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banks whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banks whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banks whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banks whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Banks extends Model
{
    use HasUlids;
    protected $table = 'm_banks';
    protected $fillable = ['code', 'name', 'company_id'];

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
