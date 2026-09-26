<?php

namespace App\Models\Master;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\JobPositionReqStatus;
use App\Enum\ManpowerRequestType;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Concerns\LogsAuditChanges;
use App\Models\Job\ManpowerRequests;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobPositions extends Model
{
    use HasUlids, SoftDeletes;
    use LogsAuditChanges;

    protected $table = 'm_job_positions';
    protected $fillable = [
        'company_id',
        'org_structure_id',
        'name',
        'parent_id',
        'code',
        'description',
        'qualification',
        'job_evaluation_score',
        'planned_manpower',
        // Kuota jabatan yang disetujui (bertambah tiap pengajuan kuota disetujui). BUKAN jumlah
        // karyawan terisi - itu dihitung realtime lewat userAssignments().
        'manpower',
        'pangkat_id',
        'kelas_id',
        'is_nakes',
        'status',
        'request_type',
        'request_by',
        'approved_by',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = ['is_nakes' => 'boolean'];

    public function competencies(): BelongsToMany
    {
        return $this->belongsToMany(
            Competencies::class,
            'm_job_position_competencies',
            'job_position_id',
            'competency_id'
        )
        ->using(JobPositionCompetencies::class)
        ->withTimestamps();
    }

    public function positionCompetencies(): HasMany
    {
        return $this->hasMany(JobPositionCompetencies::class, 'job_position_id');
    }

    public function getCompetencyNamesAttribute(): string
    {
        return $this->competencies()
            ->select('name')
            ->distinct()
            ->pluck('name')
            ->unique()
            ->sort()
            ->implode(', ');
    }

    public function getCompetencyCountAttribute(): int
    {
        return $this->competencies()->distinct()->count();
    }

    public function company()
    {
        return $this->belongsTo(Companies::class);
    }

    public function orgStructure()
    {
        return $this->belongsTo(OrgStructure::class);
    }

    /** Sama seperti UserProfiles::getDivisionAttribute() - org_structure_id jabatan bisa
     * menunjuk ke level department/division/subdivision, naik 1 tingkat ke parent kalau
     * bukan level Divisi langsung. */
    public function getDivisionAttribute()
    {
        if (! $this->orgStructure) {
            return null;
        }

        return $this->orgStructure->level === \App\Enum\OrgStructurLevel::LEVEL_DIVISION->value
            ? $this->orgStructure
            : $this->orgStructure->parent;
    }

    /**
     * Nama DEPARTEMEN jabatan ini - naik terus sampai level Departemen, berapa pun dalamnya
     * (Sub Divisi -> Divisi -> Departemen). Keputusan user 2026-09-24: yang dipakai langsung
     * departemennya, bukan divisinya.
     *
     * Dipakai mengisi job_openings.division_name di DB recruitment, yang jadi token {divisi} di
     * naskah Persetujuan Onboarding. Tetap null cuma kalau jabatannya tidak punya org_structure_id
     * sama sekali (mis. Presiden Director, yang di atas semua departemen).
     *
     * ponytail: kalau rantainya putus sebelum ketemu Departemen (tidak terjadi di master sekarang -
     * 54 divisi semuanya berinduk departemen, 20 sub divisi semuanya berinduk divisi), dipakai
     * struktur terakhir yang masih kebaca, supaya tidak jatuh jadi kosong.
     */
    public function getDepartmentNameAttribute(): ?string
    {
        $org = $this->orgStructure;
        $terakhir = $org;

        for ($i = 0; $org && $i < 5; $i++) {
            if ($org->level === \App\Enum\OrgStructurLevel::LEVEL_DEPARTMENT->value) {
                return $org->name;
            }

            $terakhir = $org;
            $org = $org->parent;
        }

        return $org?->name ?? $terakhir?->name;
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function pangkat()
    {
        return $this->belongsTo(Pangkats::class, 'pangkat_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function users()
    {
        return $this->hasMany(UserProfiles::class, 'job_position_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'request_by');
    }

    /** Alias requestedBy() - dipakai ApprovalTimelineService::build() yang generik lintas
     * modul (butuh nama relasi user() yang seragam, sama seperti AttendanceRequests/
     * ManpowerRequests/UserTransition). */
    public function user()
    {
        return $this->requestedBy();
    }

    public function approvalSnapshots()
    {
        return $this->hasMany(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::JOBPOSITION_REQUEST->value);
    }

    /** Versi tunggal approvalSnapshots() (hasMany) di atas - dipakai ApprovalPermissionService/
     * ApprovalTimelineService yang generik lintas modul (pola sama ManpowerRequests/
     * UserTransition). latestOfMany() karena secara teori 1 jabatan bisa diajukan ulang lebih
     * dari sekali (repeat_request di admin) - snapshot terbaru yang relevan. */
    public function approvalSnapshot()
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::JOBPOSITION_REQUEST->value)
            ->latestOfMany();
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id');
    }

    public function userAssignments()
    {
        return $this->hasMany(\App\Models\User\UserJobPositions::class, 'job_position_id', 'id')
            ->whereNull('end_date')
            ->where('status', 'active');
    }

    /** Pengajuan penambahan kuota (ManpowerRequests type=additional_quota) yang masih dalam
     * proses persetujuan untuk jabatan ini - dipakai JabatanPage buat arahkan tombol "Ajukan
     * Penambahan Kuota" ke halaman detail proses yang sudah berjalan, bukan biarkan pengajuan
     * duplikat (2026-08-10, instruksi user). latestOfMany() karena secara teori bisa ada lebih
     * dari 1 riwayat pengajuan kuota untuk jabatan yang sama (yang lama sudah selesai). */
    public function pendingQuotaRequest()
    {
        return $this->hasOne(ManpowerRequests::class, 'job_position_id', 'id')
            ->where('type', ManpowerRequestType::ADDITIONAL_QUOTA->value)
            ->where('status', JobPositionReqStatus::REQUESTED->value)
            ->latestOfMany();
    }

    // ── Riwayat perubahan JE (job_evaluation_score) — dipakai tab Riwayat halaman Job
    // Evaluation (Kepegawaian). JE jarang berubah, tapi tetap butuh jejak siapa & kapan. ──

    protected function auditModule(): string
    {
        return 'kepegawaian';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->company_id;
    }

    protected function auditKey(): string
    {
        return 'job_evaluation_score:' . $this->id;
    }

    protected function auditLabel(): ?string
    {
        return 'JE Jabatan — ' . $this->name;
    }

    protected function auditWatchedAttribute(): string
    {
        return 'job_evaluation_score';
    }
}
