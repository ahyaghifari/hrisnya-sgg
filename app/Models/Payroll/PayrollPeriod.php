<?php

namespace App\Models\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Support\Facades\Auth;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasUlids;
    use BelongsToCompany;

    protected $table = 'payroll_periods';

    protected $fillable = [
        'company_id',
        'start_date',
        'end_date',
        'payment_date',
        'processed_at',
        'generated_by',
        'signer_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'payment_date' => 'date',
        'processed_at' => 'datetime',
    ];

    /** Lepas scope company kalau user boleh lihat company lain di sistem Keuangan (dipakai dropdown periode). */
    public function scopeAcrossCompanies($query)
    {
        $user = Auth::user();

        return $user && $user->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEUANGAN)
            ? $query->withoutGlobalScope('company')
            : $query;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }
}
