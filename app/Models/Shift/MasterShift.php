<?php

namespace App\Models\Shift;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperMasterShift
 */
class MasterShift extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'm_shifts';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'is_overnight',
        'org_structure_id',
        'color',
        'shift_type',
    ];

    public function orgStructure()
    {
        return $this->belongsTo(\App\Models\Master\OrgStructure::class, 'org_structure_id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
