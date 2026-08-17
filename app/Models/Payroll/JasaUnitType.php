<?php

namespace App\Models\Payroll;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Katalog jasa/tunjangan per-divisi (Jasa Perujuk Rawat, Jasa Ambulan, dst) — nama bebas,
 * dikelola di halaman "Jasa Unit" (JasaUnitTypesPage). `orgStructures` = divisi yang berhak
 * atas jasa ini, dipakai JasaUnitInputPage untuk memfilter dropdown jasa sesuai divisi
 * karyawan yang dipilih.
 */
class JasaUnitType extends Model
{
    use HasUlids;
    use LogsAuditChanges;

    protected $table = 'jasa_unit_types';

    protected $fillable = [
        'company_id',
        'name',
        'code',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function orgStructures(): BelongsToMany
    {
        return $this->belongsToMany(OrgStructure::class, 'jasa_unit_type_org_structures', 'jasa_unit_type_id', 'org_structure_id');
    }

    protected function auditModule(): string
    {
        return 'payroll_mapping';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->company_id;
    }

    protected function auditKey(): string
    {
        return 'jasa_unit_type:' . $this->id;
    }

    protected function auditLabel(): ?string
    {
        return 'Jasa Unit — ' . $this->name;
    }

    protected function auditWatchedAttribute(): string
    {
        return 'name';
    }
}
