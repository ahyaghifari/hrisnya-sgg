<?php

namespace App\Models\Master\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperOvertimeTypes
 * @property string $id
 * @property string|null $company_id
 * @property string $name
 * @property string|null $day_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeTypes newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeTypes newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeTypes query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeTypes whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeTypes whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeTypes whereDayType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeTypes whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeTypes whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeTypes whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OvertimeTypes extends Model
{
    use HasUlids;

    protected $table = 'm_overtime_types';  

    protected $fillable = [
        'company_id',
        'name',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
