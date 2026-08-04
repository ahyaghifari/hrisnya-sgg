<?php

namespace App\Models\Master\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PermissionTypes extends Model
{
    use HasUlids;

    protected $table = 'm_permission_types';

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'default_days',
        'description',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
