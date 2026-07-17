<?php

namespace App\Models\Shift;

use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Header/master jadwal — ganti `MasterShift` (restrukturisasi 2026-07-18, lihat
 * planning/2026-07-18-restrukturisasi-jadwal-schedules.md). 1 baris = 1 grup jadwal
 * (Fixed/Flexible/Shift, lihat ScheduleType), isi konkretnya ada di tabel anak sesuai
 * schedule_type: fixedDays(), flexibleDays(), atau shiftTypes().
 */
class Schedule extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'schedules';

    protected $fillable = [
        'company_id',
        'name',
        'schedule_type',
        'org_structure_id',
        'color',
        'flexible_checkin_from',
        'flexible_checkin_until',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function orgStructure()
    {
        return $this->belongsTo(OrgStructure::class, 'org_structure_id');
    }

    public function fixedDays(): HasMany
    {
        return $this->hasMany(ScheduleFixedDay::class, 'schedule_id');
    }

    public function flexibleDays(): HasMany
    {
        return $this->hasMany(ScheduleFlexibleDay::class, 'schedule_id');
    }

    public function shiftTypes(): HasMany
    {
        return $this->hasMany(ScheduleShiftType::class, 'schedule_id');
    }

    public function shiftTemplates(): HasMany
    {
        return $this->hasMany(ScheduleShiftTemplate::class, 'schedule_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class, 'schedule_id');
    }
}
