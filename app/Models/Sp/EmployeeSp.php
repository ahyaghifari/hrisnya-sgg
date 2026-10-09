<?php

namespace App\Models\Sp;

use App\Contracts\Signable;
use App\Enum\CustomPermissionEnum;
use App\Enum\DocumentType;
use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpSourceType;
use App\Helpers\PermissionHelper;
use App\Models\Coaching\Coaching;
use App\Models\Concerns\HasDocumentSignatures;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * implements Signable (TTE Fase 1, docs/2026-09-28-tte-qr-internal-fase-1.md §2) - Berita Acara
 * (SpReport) SENGAJA Signable TERPISAH (dokumen beda, instruksi user 2026-09-28), bukan ikut
 * ditandatangani bareng SP induknya.
 */
class EmployeeSp extends Model implements Signable
{
    use HasUlids;
    use HasDocumentSignatures;

    protected $fillable = [
        'nomor',
        'user_id',
        'company_id',
        'sp_level',
        'source_type',
        'source_id',
        'start_date',
        'end_date',
        'created_by',
        'knows_by',
        'cancelled_at',
        'cancelled_reason',
        'cancelled_by',
        'revised_at',
    ];

    /** Field yang masuk signablePayload() - dikunci begitu dokumen FIX (Document::fixed_at). */
    private const CONTENT_FIELDS = ['nomor', 'user_id', 'sp_level', 'start_date', 'end_date', 'created_by', 'knows_by'];

    protected static function booted(): void
    {
        static::updating(function (EmployeeSp $sp) {
            if ($sp->isDirty(self::CONTENT_FIELDS) && $sp->isFixed()) {
                throw new \LogicException('Surat Peringatan sudah dikunci (FIX), isinya tidak bisa diubah.');
            }
        });
    }

    protected $casts = [
        'sp_level' => SpLevel::class,
        'source_type' => SpSourceType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'cancelled_at' => 'datetime',
        'revised_at' => 'datetime',
    ];

    /**
     * SP aktif = source of truth bisnis, selalu live-query (planning/2026-09-23-sp-dan-merit-point.md
     * §2.2) - JANGAN baca dari cache user_hr_balances buat validasi. SP yang dibatalkan
     * (cancelled_at terisi) TIDAK PERNAH dianggap aktif, walau tanggalnya masih berlaku
     * (instruksi user 2026-09-25, fitur Batal SP).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at')
            ->whereDate('start_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
            });
    }

    /** Sudah diterbitkan = sudah punya baris registry `documents` (lihat SpDetailPage::getIsDraftProperty);
     *  draft belum boleh dilihat karyawan yang bersangkutan. */
    public function scopeIssued($query)
    {
        return $query->whereHas('document');
    }

    public function isIssued(): bool
    {
        return $this->document !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * HC (izin SISTEM_PEMBINAAN_KARYAWAN + View:EmployeeSp, company sama/global) bisa lihat SP
     * mana pun di company-nya seperti biasa. Ditambah: "Mengetahui" yang DITUNJUK di SP ini juga
     * boleh lihat (instruksi user 2026-09-28) - dia perlu tahu isi surat yang mau/sudah
     * ditandatanganinya, bukan cuma modal ringkas di halaman tanda tangan. HANYA lihat - bukan
     * berarti boleh edit, lihat SpDetailPage::getCanManageProperty() untuk gerbang tulis.
     */
    public function canBeViewedBy(User $user): bool
    {
        if ($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $user->can('View:EmployeeSp')) {
            return $this->company_id === $user->company_id || PermissionHelper::hasGlobalRole($user);
        }

        return $user->id === $this->knows_by;
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** "Mengetahui" - pimpinan/HC Supervisor/Manager yang menyetujui surat ini (instruksi user
     * 2026-09-25, sesuai flowchart approval HC-GA). WAJIB diisi saat SP dibuat (instruksi user
     * 2026-09-28, revisi dari "boleh menyusul/offline verbal") - lihat CreateSpPage::rules()
     * dan migration require_knows_by_on_employee_sps_table. */
    public function knowsBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'knows_by');
    }

    /** Poin-poin pelanggaran (instruksi user 2026-09-25) - isi surat, menggantikan reason_id/reason_note lama. */
    public function items(): HasMany
    {
        return $this->hasMany(SpItem::class, 'sp_id')->orderBy('sort_order');
    }

    /** Berita Acara (instruksi user 2026-09-25) - 1:1, catatan investigasi HR-internal. */
    public function report(): HasOne
    {
        return $this->hasOne(SpReport::class, 'sp_id');
    }

    /**
     * Coaching yang jadi sumber SP ini (instruksi user 2026-09-23) - penanda saja bahwa
     * karyawan sudah pernah dicoaching sebelum SP ini terbit, dipilih manual saat SP dibuat
     * (CreateSpPage/EmployeeSpResource), BUKAN pemicu otomatis. Null kalau source_type=MANUAL
     * (source_id null) atau sumbernya bukan Coaching.
     * ponytail: belongsTo polos ke `source_id` tanpa filter source_type di query - aman selama
     * SpSourceType cuma COACHING|MANUAL (source_id selalu null utk MANUAL). Tambahkan filter
     * eksplisit kalau source_id nanti dipakai source_type lain (mis. ATTENDANCE, §5).
     */
    public function coaching(): BelongsTo
    {
        return $this->belongsTo(Coaching::class, 'source_id');
    }

    /** @return array<string, mixed> */
    public function signablePayload(): array
    {
        return [
            'nomor' => $this->nomor,
            'user_id' => $this->user_id,
            'sp_level' => $this->sp_level->value,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'created_by' => $this->created_by,
            'knows_by' => $this->knows_by,
            'items' => $this->items->sortBy('sort_order')->pluck('text')->values()->all(),
        ];
    }

    public function documentNumber(): string
    {
        return $this->nomor;
    }

    public function signSummary(): array
    {
        $this->loadMissing(['user.activeJobPosition.jobPosition', 'items']);

        return array_filter([
            'Karyawan' => $this->user?->name,
            'ID Karyawan' => $this->user?->nip,
            'Jabatan' => $this->user?->activeJobPosition?->jobPosition?->name,
            'Unit Kerja' => $this->user?->activeJobPosition?->jobPosition?->department_name,
            'Jenis SP' => $this->sp_level->getLabel(),
            'Berlaku' => $this->start_date?->translatedFormat('d M Y') . ($this->end_date ? ' - ' . $this->end_date->translatedFormat('d M Y') : ' (tanpa batas akhir)'),
            'Poin pelanggaran' => $this->items->count() . ' poin',
        ]);
    }

    public function documentTypeLabel(): string
    {
        return "Surat Peringatan ({$this->sp_level->getLabel()})";
    }

    public function documentType(): DocumentType
    {
        return DocumentType::SP;
    }

    public function isRevoked(): bool
    {
        return $this->isCancelled();
    }

    public function revokedReason(): ?string
    {
        return $this->cancelled_reason;
    }

    /** SP yang dibuat dari BA alur baru (instruksi user 2026-10-01) - karyawan ikut menandatangani.
     *  SP lama (tanpa BA alur baru) tetap 2 penanda tangan supaya tidak jadi "belum lengkap". */
    public function requiresEmployeeSignature(): bool
    {
        return $this->report !== null && ! $this->report->isLegacy();
    }

    public function expectedSignerRoles(): array
    {
        $roles = [
            'created_by' => 'Dibuat oleh',
            'knows_by' => 'Mengetahui',
        ];

        return $this->requiresEmployeeSignature() ? $roles + ['employee' => 'Karyawan yang Bersangkutan'] : $roles;
    }

    /** Urutan TTD SP baru: pembuat, mengetahui, lalu karyawan - karyawan baru ditugaskan setelah
     *  dua yang lain selesai. */
    public function assignedSigners(): array
    {
        $signers = [
            'created_by' => $this->createdBy,
            'knows_by' => $this->knowsBy,
        ];

        if ($this->requiresEmployeeSignature()) {
            $signedRoles = $this->document?->signatures()->pluck('signer_role') ?? collect();
            $signers['employee'] = $signedRoles->contains('created_by') && $signedRoles->contains('knows_by') ? $this->user : null;
        }

        return $signers;
    }
}
