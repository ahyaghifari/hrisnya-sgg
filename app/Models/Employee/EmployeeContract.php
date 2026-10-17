<?php

namespace App\Models\Employee;

use App\Models\ContractSubmission;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\OrientationSubmission;
use App\Models\User\UserTransition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Ledger riwayat orientasi DAN kontrak per karyawan (satu tabel, kolom `type`): 1 baris = 1 periode.
 * Alurnya selalu orientasi dulu, lalu kontrak. Append-only: perpanjangan = baris baru
 * (previous_contract_id ke baris sebelumnya).
 *
 * - type 'orientasi': dibuat lewat Rekrutmen (source 'recruitment') atau perpanjangan penilaian
 *   (source 'extension'); punya duration_months. INDUK dari orientation_submissions.
 * - type 'kontrak': periode kontrak yang SUDAH disetujui (Pengangkatan Orientasi ke Kontrak, atau
 *   tiap Perpanjangan Kontrak); punya employee_type_id. INDUK dari contract_submissions.
 *
 * Pengganti user_contracts + user_orientations (dikunci user_id): data disalin dengan id yang sama
 * saat migration create_employee_contracts_table, tabel lama dibiarkan beku. contract_start_date /
 * contract_end_date lama kini start_date / end_date.
 */
class EmployeeContract extends Model
{
    use HasUlids;

    public const TYPE_ORIENTASI = 'orientasi';

    public const TYPE_KONTRAK = 'kontrak';

    protected $table = 'employee_contracts';

    protected $fillable = [
        'employee_id',
        'company_id',
        'type',
        'employee_type_id',
        'start_date',
        'end_date',
        'duration_months',
        'file_path',
        'source',
        'source_transition_id',
        'previous_contract_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_months' => 'integer',
    ];

    public function scopeKontrak(Builder $query): void
    {
        $query->where($query->qualifyColumn('type'), self::TYPE_KONTRAK);
    }

    public function scopeOrientasi(Builder $query): void
    {
        $query->where($query->qualifyColumn('type'), self::TYPE_ORIENTASI);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeTypes::class, 'employee_type_id');
    }

    public function sourceTransition(): BelongsTo
    {
        return $this->belongsTo(UserTransition::class, 'source_transition_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_contract_id');
    }

    /** Pengajuan perpanjangan kontrak yang menempel ke periode kontrak ini (anak). */
    public function contractSubmissions(): HasMany
    {
        return $this->hasMany(ContractSubmission::class, 'employee_contract_id')->latest();
    }

    /** Pengajuan penilaian untuk periode orientasi ini (anak). */
    public function orientationSubmissions(): HasMany
    {
        return $this->hasMany(OrientationSubmission::class, 'employee_contract_id')->latest();
    }

    public function isActive(): bool
    {
        return $this->end_date && ! $this->end_date->isPast();
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    /** Periode kontrak TERBARU per karyawan (baris type kontrak dengan start_date terbesar), di-scope ke
     * 1 company - dipakai widget dashboard mana pun yang butuh "kontrak aktif sekarang" tanpa N+1.
     * Hasil di-key employee_id.
     *
     * @return \Illuminate\Support\Collection<string, self>
     */
    public static function latestKontrakPerEmployeeInCompany(string $companyId): \Illuminate\Support\Collection
    {
        return static::query()
            ->kontrak()
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('end_date')
            ->with(['employee.jobPosition', 'employeeType'])
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($group) => $group->sortByDesc('start_date')->first());
    }
}
