<?php

namespace App\Models\Shift;

use App\Models\Master\Attendance\OvertimeTypes;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftOvertimeCosts extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'shift_overtime_costs';

    protected $fillable = [
        'shift_id',
        'overtime_type_id',
        'company_id',
        'maximum_overtime_hours',
        'overtime_rate',
    ];

    public function shift()
    {
        return $this->belongsTo(MasterShift::class, 'shift_id');
    }

    public function overtimeType()
    {
        return $this->belongsTo(OvertimeTypes::class, 'overtime_type_id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
