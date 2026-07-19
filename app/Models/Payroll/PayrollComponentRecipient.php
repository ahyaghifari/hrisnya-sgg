<?php

namespace App\Models\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penerima komponen gaji per tipe karyawan (halaman "Penerima Komponen Gaji") — 1 baris
 * = "tipe karyawan ini berhak dapat komponen itu di company ini". TIDAK ADA baris untuk
 * kombinasi company+component tertentu = TIDAK ADA yang eligible (default ketat, disengaja).
 *
 * @mixin IdeHelperPayrollComponentRecipient
 */
class PayrollComponentRecipient extends Model
{
    use HasUlids;

    protected $table = 'payroll_component_recipients';

    protected $fillable = [
        'company_id',
        'component',
        'employee_type_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeTypes::class, 'employee_type_id');
    }

    /**
     * Dipanggil PayrollService::runComponent() sebelum kalkulasi komponen apa pun. Karyawan
     * tanpa employee_type_id (belum diisi profilnya) otomatis TIDAK eligible — konsisten
     * sama prinsip default ketat, bukan celah.
     */
    public static function isEligible(?string $companyId, string $component, ?string $employeeTypeId): bool
    {
        if (! $companyId || ! $employeeTypeId) {
            return false;
        }

        return static::query()
            ->where('company_id', $companyId)
            ->where('component', $component)
            ->where('employee_type_id', $employeeTypeId)
            ->exists();
    }

    /**
     * Dipanggil PayrollService::generatePayroll() SEBELUM baris Payroll dibuat sama sekali —
     * tipe karyawan yang tidak terdaftar sebagai penerima komponen APA PUN (belum di-assign
     * ke satu pun komponen di halaman "Penerima Komponen Gaji") tidak ikut digenerate, jadi
     * tidak dapat slip gaji. Beda dari isEligible() yang cek per-komponen — ini cek "terdaftar
     * di mana pun", dipakai buat gate seluruh payroll-nya, bukan cuma 1 komponen.
     */
    public static function isRegisteredAnywhere(?string $companyId, ?string $employeeTypeId): bool
    {
        if (! $companyId || ! $employeeTypeId) {
            return false;
        }

        return static::query()
            ->where('company_id', $companyId)
            ->where('employee_type_id', $employeeTypeId)
            ->exists();
    }
}
