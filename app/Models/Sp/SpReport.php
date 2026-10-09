<?php

namespace App\Models\Sp;

use App\Contracts\Signable;
use App\Enum\DocumentType;
use App\Enum\Sp\SpReportDecision;
use App\Enum\Sp\SpReportStatus;
use App\Models\Concerns\HasDocumentSignatures;
use App\Models\Invitation;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;

/**
 * Berita Acara. Dua generasi:
 * - BA lama (`status` NULL): 1:1 bagian dari employee_sps, HR-internal, penanda tangan dipinjam
 *   dari SP induk (created_by/knows_by) dan ditandatangani bareng SP-nya (instruksi user 2026-09-25).
 * - BA baru (`status` terisi, instruksi user 2026-10-01): proses utama - HC bikin BA dulu,
 *   karyawan mengisi klarifikasi, HC memparafrase + analisis, lalu ditandatangani karyawan,
 *   pembuat, dan yang mengetahui. SP baru dibuat setelah BA selesai & diputuskan layak SP
 *   (`sp_id` diisi saat itu). Alurnya ada di App\Services\Sp\SpReportService.
 *
 * implements Signable TERPISAH dari EmployeeSp (instruksi user 2026-09-28: dokumen beda, TTD
 * dibedakan) - registry/hash/kode verifikasi sendiri, BUKAN ikut paket SP induknya.
 */
class SpReport extends Model implements Signable
{
    use HasUlids;
    use HasDocumentSignatures;

    /** Isi dokumen - dikunci begitu BA selesai (lihat booted()). */
    private const CONTENT_FIELDS = [
        'nomor', 'kejadian', 'subject_clarification',
        'klarifikasi_parafrase', 'analysis', 'kesimpulan', 'followup_plan',
    ];

    protected $fillable = [
        'sp_id',
        'invitation_id',
        'coaching_id',
        'revised_at',
        'signed_file_path',
        'signed_file_name',
        'signed_uploaded_at',
        'signed_uploaded_by',
        'user_id',
        'company_id',
        'created_by',
        'knows_by',
        'supervisor_id',
        'status',
        'keputusan',
        'nomor',
        'kejadian',
        'subject_clarification',
        'klarifikasi_parafrase',
        'klarifikasi_diajukan_at',
        'klarifikasi_diisi_hc',
        'riwayat_klarifikasi',
        'analysis',
        'kesimpulan',
        'followup_plan',
    ];

    protected $casts = [
        'status' => SpReportStatus::class,
        'keputusan' => SpReportDecision::class,
        'klarifikasi_diajukan_at' => 'datetime',
        'revised_at' => 'datetime',
        'signed_uploaded_at' => 'datetime',
        'klarifikasi_diisi_hc' => 'boolean',
        'riwayat_klarifikasi' => 'array',
    ];

    protected static function booted(): void
    {
        // BA yang sudah SELESAI atau sudah ADA yang menandatangani (BA lama ikut TTD SP-nya) tidak
        // boleh berubah isinya - hash TTD-nya dihitung dari isi ini. keputusan/sp_id bukan bagian
        // isi, tetap boleh diisi setelahnya.
        static::updating(function (SpReport $report) {
            if ($report->isDirty(self::CONTENT_FIELDS) && ($report->getOriginal('status') === SpReportStatus::SELESAI || $report->isFixed())) {
                throw new \LogicException('Berita Acara sudah ditandatangani, isinya tidak bisa diubah.');
            }
        });
    }

    public function sp(): BelongsTo
    {
        return $this->belongsTo(EmployeeSp::class, 'sp_id');
    }

    /** Coaching yang mendahului klarifikasi ini (opsional, dipilih HC di BA). */
    public function coaching(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Coaching\Coaching::class, 'coaching_id');
    }

    public function signedUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_uploaded_by');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class, 'invitation_id');
    }

    public function dasarItems(): HasMany
    {
        return $this->hasMany(SpReportDasarItem::class, 'sp_report_id')->orderBy('sort_order');
    }

    /** Teks mentah semua item (dipakai hash TTD & cek terisi). BA lama = 1 item berisi teks lamanya, jadi hash tetap sama. */
    public function getDasarKlarifikasiAttribute(): string
    {
        return $this->dasarItems->pluck('text')->implode("\n");
    }

    /** HTML tampilan: 1 item apa adanya, lebih dari 1 dinomori otomatis lewat <ol>. */
    public function dasarKlarifikasiHtml(): HtmlString
    {
        $items = $this->dasarItems;

        return new HtmlString($items->count() > 1
            ? '<ol class="dasar-items">' . $items->map(fn ($i) => "<li>{$i->text}</li>")->implode('') . '</ol>'
            : (string) $items->first()?->text);
    }

    /** Klarifikasi karyawan: diisikan HC = HTML Trix apa adanya, tulisan karyawan sendiri = teks polos (di-escape). */
    public function subjectClarificationHtml(?string $text = null, ?bool $byHc = null): HtmlString
    {
        $text ??= $this->subject_clarification;

        return new HtmlString(($byHc ?? $this->klarifikasi_diisi_hc) ? (string) $text : nl2br(e($text)));
    }

    /** @param  array<int, string|array{reason_id?:?string, note?:?string}>  $items  string = teks lama tanpa master */
    public function syncDasarItems(array $items): void
    {
        // Dasar Klarifikasi ikut di-hash TTD, jadi sama terkunci dengan kolom isi lainnya.
        if ($this->status === SpReportStatus::SELESAI || $this->isFixed()) {
            throw new \LogicException('Berita Acara sudah ditandatangani, isinya tidak bisa diubah.');
        }

        $this->dasarItems()->delete();

        $rows = array_map(fn ($item) => SpReason::composeItem(is_string($item) ? trim($item) : $item), $items);

        foreach (array_values(array_filter($rows, fn ($row) => filled($row['text']))) as $index => $row) {
            $this->dasarItems()->create(['text' => $row['text'], 'reason_id' => $row['reason_id'], 'note' => $row['note'], 'sort_order' => $index]);
        }

        $this->unsetRelation('dasarItems');
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

    public function knowsBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'knows_by');
    }

    /** Atasan langsung karyawan - penanda tangan tambahan (null kalau tidak ada / sama dengan pembuat). */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** BA generasi lama (bagian dari SP) - tidak punya status/alur klarifikasi. */
    public function isLegacy(): bool
    {
        return $this->status === null;
    }

    /** @return array<string, mixed> */
    public function signablePayload(): array
    {
        $payload = [
            'nomor' => $this->nomor,
            'sp_id' => $this->sp_id,
            'kejadian' => $this->kejadian,
            'dasar_klarifikasi' => $this->dasar_klarifikasi,
            'subject_clarification' => $this->subject_clarification,
            'analysis' => $this->analysis,
            'kesimpulan' => $this->kesimpulan,
            'followup_plan' => $this->followup_plan,
        ];

        // Key baru HANYA untuk BA baru - BA lama sudah punya hash dari payload tanpa key ini,
        // menambahkannya akan membuat verifikasi TTD lama mismatch.
        if (! $this->isLegacy()) {
            // sp_id baru terisi SETELAH BA ditandatangani (saat SP dibuat dari BA ini) - kalau ikut
            // di-hash, verifikasi TTD BA langsung mismatch begitu SP-nya dibuat.
            unset($payload['sp_id']);
            $payload['user_id'] = $this->user_id;
            $payload['klarifikasi_parafrase'] = $this->klarifikasi_parafrase;
        }

        return $payload;
    }

    public function documentNumber(): string
    {
        return $this->nomor ?? 'BA-' . ($this->sp_id ?? $this->id);
    }

    public function signSummary(): array
    {
        $this->loadMissing(['user.activeJobPosition.jobPosition']);

        return array_filter([
            'Karyawan' => $this->user?->name,
            'ID Karyawan' => $this->user?->nip,
            'Jabatan' => $this->user?->activeJobPosition?->jobPosition?->name,
            'Unit Kerja' => $this->user?->activeJobPosition?->jobPosition?->department_name,
            'Perihal' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $this->kejadian)), 160) ?: null,
        ]);
    }

    public function documentTypeLabel(): string
    {
        return 'Berita Acara';
    }

    public function documentType(): DocumentType
    {
        return DocumentType::BERITA_ACARA;
    }

    /** Ikut status batal SP induknya - Berita Acara tidak punya jalur cancel sendiri. */
    public function isRevoked(): bool
    {
        return $this->sp?->isCancelled() ?? false;
    }

    public function revokedReason(): ?string
    {
        return $this->sp?->cancelled_reason;
    }

    public function expectedSignerRoles(): array
    {
        if ($this->isLegacy()) {
            return [
                'created_by' => 'Dibuat oleh',
                'knows_by' => 'Mengetahui',
            ];
        }

        return $this->orderedForSigning($this->baseSignerRoles());
    }

    /** BA yang pernah direvisi: HC pembuat menandatangani lebih dulu, lalu atasan langsung, mengetahui, karyawan. */
    private function orderedForSigning(array $roles): array
    {
        if (! $this->revised_at) {
            return $roles;
        }

        return array_intersect_key(array_replace(array_flip(['created_by', 'supervisor', 'knows_by', 'employee']), $roles), $roles);
    }

    private function baseSignerRoles(): array
    {
        // Urutan = urutan tanda tangan. Tata letak PDF: Atasan Langsung | Karyawan, lalu "Mengetahui," dua penanda tangan (cukup nama + jabatan aslinya).
        // Atasan langsung = HC pembuat -> HC menempati slot Atasan Langsung dan hanya Manajer di bawah "Mengetahui,".
        if ($this->supervisor_id && $this->supervisor_id === $this->created_by) {
            return [
                'created_by' => 'Atasan Langsung',
                'knows_by' => 'Mengetahui',
                'employee' => 'Karyawan yang Bersangkutan',
            ];
        }

        return $this->supervisor_id
            ? [
                'supervisor' => 'Atasan Langsung',
                'created_by' => 'Mengetahui',
                'knows_by' => 'Mengetahui',
                'employee' => 'Karyawan yang Bersangkutan',
            ]
            : [
                'created_by' => 'Dibuat oleh',
                'knows_by' => 'Mengetahui',
                'employee' => 'Karyawan yang Bersangkutan',
            ];
    }

    /** Penanda tangan BA baru per peran (peran 'supervisor' hanya ada kalau supervisor_id terisi). */
    public function signerUsers(): array
    {
        $all = [
            'supervisor' => $this->supervisor,
            'created_by' => $this->createdBy,
            'knows_by' => $this->knowsBy,
            'employee' => $this->user,
        ];

        // Urutan mengikuti expectedSignerRoles() (berbeda untuk BA yang direvisi).
        return collect(array_keys($this->expectedSignerRoles()))->mapWithKeys(fn ($role) => [$role => $all[$role]])->all();
    }

    /**
     * BA lama: dipinjam dari SP induk. BA baru: hanya terisi saat status MENUNGGU_TTD, berurutan
     * sesuai expectedSignerRoles(): atasan langsung (kalau ada), HC pembuat, mengetahui, dan
     * karyawan terakhir (instruksi user 2026-10-07).
     * Pengajuan ulang klarifikasi oleh karyawan hanya mungkin selama belum ada tanda tangan.
     */
    public function assignedSigners(): array
    {
        if ($this->isLegacy()) {
            return [
                'created_by' => $this->sp?->createdBy,
                'knows_by' => $this->sp?->knowsBy,
            ];
        }

        // TTD digital ditahan (config sp.digital_signing): BA ditandatangani langsung lalu file diunggah.
        if ($this->status !== SpReportStatus::MENUNGGU_TTD || ! \App\Services\Sp\SpReportService::digitalSigningEnabled()) {
            return ['employee' => null, 'created_by' => null, 'knows_by' => null, 'supervisor' => null];
        }

        $signedRoles = $this->document?->signatures()->pluck('signer_role') ?? collect();
        $assigned = [];
        $previousDone = true;

        foreach ($this->signerUsers() as $role => $user) {
            $assigned[$role] = $previousDone ? $user : null;
            $previousDone = $previousDone && $signedRoles->contains($role);
        }

        return $assigned;
    }
}
