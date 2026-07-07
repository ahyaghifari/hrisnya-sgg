<?php

namespace App\Models\DoctorService;

use App\Models\Concerns\BelongsToCompanyOrShared;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMasterDoctorService
 */
class MasterDoctorService extends Model
{
    use HasUlids;
    use BelongsToCompanyOrShared;

    protected $table = 'm_doctor_services';

    protected $fillable = [
        'company_id',
        'name',
        'calculation_type',
        'amount',
        'percentage',
        'formula_code',
        'notes',
        'is_active',
    ];
}
