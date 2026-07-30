<?php

namespace App\Models\Master\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperPermissionTypes
 * @property string $id
 * @property string|null $company_id
 * @property string $name
 * @property string|null $category
 * @property int|null $default_days
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionTypes newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionTypes newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionTypes query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionTypes whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionTypes whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionTypes whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionTypes whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionTypes whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionTypes whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PermissionTypes extends Model
{
    use HasUlids;

    protected $table = 'm_permission_types';

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'default_days',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
