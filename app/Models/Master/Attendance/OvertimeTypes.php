<?php

namespace App\Models\Master\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperOvertimeTypes
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
