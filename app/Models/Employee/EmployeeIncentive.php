<?php

namespace App\Models\Employee;

use App\Casts\PayrollEncrypted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Insentif per karyawan (boleh banyak baris per karyawan; metode "master" di IncentiveCalculator
 * menjumlahkan semua yang is_active). Pengganti tabel incentives (dikunci user_id): data disalin saat
 * migration create_employee_incentives_table, tabel lama dibiarkan beku.
 *
 * Company sengaja tidak disimpan sebagai kolom sendiri — sudah pasti bisa diturunkan dari
 * employee->company_id (1 karyawan cuma di 1 company), jadi scoping company dilakukan lewat
 * relasi employee, bukan kolom company_id yang bisa nyasar dari data aslinya.
 */
class EmployeeIncentive extends Model
{
    use HasUlids;

    protected $table = 'employee_incentives';

    protected $fillable = [
        'employee_id',
        'name',
        'incentive_date',
        'amount',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'incentive_date' => 'date',
            'is_active' => 'boolean',
            'amount' => PayrollEncrypted::class,
        ];
    }

    protected static function booted(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        static::addGlobalScope('company', function (Builder $query) {
            $user = Auth::user();

            if ($user && $user->company_id && ! $user->hasRole('Super Admin')) {
                $query->whereHas('employee', fn ($q) => $q->where('company_id', $user->company_id));
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
