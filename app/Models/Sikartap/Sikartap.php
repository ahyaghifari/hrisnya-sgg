<?php

namespace App\Models\Sikartap;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Sikartap\SikartapDocumentType;
use App\Enum\Sikartap\SikartapSettingEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sikartap extends Model
{
    use HasUlids;

    protected $table = 'sikartap';

    protected $fillable = [
        'user_id',
        'status_berkas',
        'berkas_lengkap',
        'status_tes',
        'nilai_tes',
        'nilai_psikotes',
        'catatan_tes',
        'status_wawancara',
        'catatan_wawancara',
        'status_mcu',
        'catatan_mcu',
        'status_keputusan_direktur',
        'is_diskualifikasi',
        'alasan_diskualifikasi',
    ];

    protected function casts(): array
    {
        return [
            'berkas_lengkap' => 'boolean',
            'is_diskualifikasi' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SikartapDocumentAttachment::class);
    }

    public function testSchedule(): HasOne
    {
        return $this->hasOne(SikartapTestSchedule::class);
    }

    public function interviewSchedule(): HasOne
    {
        return $this->hasOne(SikartapInterviewSchedule::class);
    }

    public function mcuNurseAssessment(): HasOne
    {
        return $this->hasOne(SikartapMcuNurseAssessment::class);
    }

    public function mcuLabResult(): HasOne
    {
        return $this->hasOne(SikartapMcuLabResult::class);
    }

    public function mcuRadiologyResult(): HasOne
    {
        return $this->hasOne(SikartapMcuRadiologyResult::class);
    }

    public function mcuDoctorExamination(): HasOne
    {
        return $this->hasOne(SikartapMcuDoctorExamination::class);
    }

    public function ideInovasi(): HasOne
    {
        return $this->hasOne(SikartapIdeInovasi::class);
    }

    /** Snapshot approval Keputusan Direktur (Fase 4) — module 1:1 sama pola AttendanceRequests. */
    public function approvalSnapshot(): HasOne
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION->value);
    }

    public function approvalHistories(): HasMany
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id');
    }

    /** Semua dokumen wajib (§ SikartapDocumentType::isRequired()) sudah diupload — gate legacy
     * $is_complete di verifikasi_berkas.php, dicek berdasar keberadaan upload, bukan status. */
    public function hasAllRequiredDocumentsUploaded(): bool
    {
        $uploadedTypes = $this->documents->pluck('doc_type')->map(fn ($type) => $type->value)->all();

        foreach (SikartapDocumentType::cases() as $type) {
            if ($type->isRequired() && ! in_array($type->value, $uploadedTypes, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Satu sumber kebenaran gate lulus ke keputusan direktur — pengganti
     * get_keputusan_akhir() yang di legacy diduplikasi identik di 2 file
     * (detail_pengajuan.php & detail_pengajuan_direktur.php).
     */
    public function isEligibleForDirectorDecision(): bool
    {
        if ($this->is_diskualifikasi) {
            return false;
        }

        return $this->status_berkas === 'approved'
            && $this->berkas_lengkap
            && $this->status_tes === 'lulus'
            && $this->status_wawancara === 'disarankan'
            && $this->ideInovasiApproved()
            && (! $this->requiresMcu() || $this->status_mcu === 'fit');
    }

    /**
     * Keputusan akhir "GAGAL" — rewrite get_keputusan_akhir() legacy (history_pengajuan.php),
     * ditolak DI FASE MANAPUN (bukan cuma keputusan direktur). Dipakai canReapplyAfterRejection()
     * buat nentuin apakah karyawan boleh mulai pengajuan baru.
     */
    public function isGagal(): bool
    {
        return $this->status_keputusan_direktur === 'rejected'
            || $this->status_berkas === 'rejected'
            || $this->status_tes === 'tidak_lulus'
            || $this->status_wawancara === 'tidak_disarankan'
            || $this->status_mcu === 'unfit';
    }

    /**
     * Boleh mulai pengajuan baru (rewrite tombol "Buat Pengajuan Baru" history_pengajuan.php
     * legacy) — HANYA kalau ditolak (isGagal), BUKAN didiskualifikasi. Diskualifikasi = permanen,
     * legacy eksplisit: "proses pengajuan Anda telah dihentikan ... tidak dapat dilanjutkan
     * kembali" - get_keputusan_akhir() legacy sendiri tidak pernah cek is_diskualifikasi sama
     * sekali, jadi tombol reapply memang sengaja tidak pernah muncul buat kasus itu.
     */
    public function canReapplyAfterRejection(): bool
    {
        return $this->isGagal() && ! $this->is_diskualifikasi;
    }

    /** Company ini mensyaratkan tahap MCU atau tidak — App\Enum\Sikartap\SikartapSettingEnum::WAJIB_MCU. */
    public function requiresMcu(): bool
    {
        return Setting::resolve(SikartapSettingEnum::WAJIB_MCU->value, $this->user?->company_id, default: true);
    }

    /** Company ini mensyaratkan tahap Ide Inovasi atau tidak — SikartapSettingEnum::WAJIB_IDE_INOVASI. */
    public function requiresIdeInovasi(): bool
    {
        return Setting::resolve(SikartapSettingEnum::WAJIB_IDE_INOVASI->value, $this->user?->company_id, default: false);
    }

    /** Gate blocking (tahap 1/atasan) — tahap direktur/komisaris opsional, tidak masuk sini. */
    public function ideInovasiApproved(): bool
    {
        return ! $this->requiresIdeInovasi() || $this->ideInovasi?->status_atasan === 'approved';
    }

    /**
     * Node level-2 (Divisi) org struktur tempat jabatan karyawan ini bernaung — dasar penentuan
     * atasan (Kabag/Kabid) penilai ide inovasi. Kabag/Kabid itu jabatan per DIVISI, bukan per
     * department (level 1) — 1 department bisa punya banyak divisi/Kabag berbeda, jadi salah
     * kalau dilumpin ke root. Reuse JobPositions::getDivisionAttribute() (helper yang sudah ada,
     * dipakai juga UserProfiles) - naik 1 tingkat ke parent kalau org_structure jabatan bukan
     * level Divisi langsung (misal ada di Subdivisi).
     */
    public function ideInovasiOrgDivision(): ?\App\Models\Master\OrgStructure
    {
        return $this->user?->activeJobPosition?->jobPosition?->division;
    }

    /**
     * Semua user yang berhak review sebagai atasan (tahap 1) — union dari 2 sumber, siapa saja
     * yang match salah satunya boleh review (bukan saling menggantikan):
     * 1. Head node org struktur (m_org_structures.head_id) — nyaris selalu kosong di data riil
     *    (2026-08-25: cuma 1 dari 84 baris), jadi tidak bisa diandalkan sendirian.
     * 2. Penunjukan manual admin (SikartapIdeInovasiReviewer) — fallback/pelengkap, siapa saja
     *    bisa ditunjuk, tidak harus pemegang jabatan tertentu.
     * Instruksi user (2026-08-25): atasan asli (sumber 1) HARUS tetap bisa review walau lupa
     * ditunjuk manual — makanya union, bukan salah satu doang.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function ideInovasiAtasanCandidates(): \Illuminate\Support\Collection
    {
        $divisi = $this->ideInovasiOrgDivision();

        if (! $divisi) {
            return collect();
        }

        $candidates = collect([$divisi->head])->filter();

        $ditunjuk = SikartapIdeInovasiReviewer::where('org_structure_id', $divisi->id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        return $candidates->concat($ditunjuk)->unique('id')->values();
    }

    public function canReviewIdeInovasiAsAtasan(User $user): bool
    {
        return $this->ideInovasiAtasanCandidates()->contains('id', $user->id);
    }
}
