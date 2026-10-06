<?php

namespace App\Models\Sp;

use App\Contracts\Signable;
use App\Enum\DocumentType;
use App\Enum\Sp\SpReportDecision;
use App\Enum\Sp\SpReportStatus;
use App\Models\Concerns\HasDocumentSignatures;
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
            'Untuk' => $this->user?->name,
            'Jabatan' => $this->user?->activeJobPosition?->jobPosition?->name,
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

        return [
            'created_by' => 'Dibuat oleh',
            'knows_by' => 'Mengetahui',
        ] + ($this->supervisor_id ? ['supervisor' => 'Atasan Langsung'] : [])
          + ['employee' => 'Karyawan yang Bersangkutan'];
    }

    /**
     * BA lama: dipinjam dari SP induk. BA baru: hanya terisi saat status MENUNGGU_TTD, dan
     * PEMBUAT (HC) menandatangani lebih dulu (instruksi user 2026-10-02: Parafrase & Analisis
     * HC sudah merupakan Berita Acara-nya), lalu yang mengetahui, atasan langsung (kalau ada), dan
     * karyawan terakhir (instruksi user 2026-10-06).
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

        if ($this->status !== SpReportStatus::MENUNGGU_TTD) {
            return ['employee' => null, 'created_by' => null, 'knows_by' => null, 'supervisor' => null];
        }

        $signedRoles = $this->document?->signatures()->pluck('signer_role') ?? collect();
        $creatorSigned = $signedRoles->contains('created_by');
        $knowsSigned = $creatorSigned && $signedRoles->contains('knows_by');
        $supervisorDone = $knowsSigned && (! $this->supervisor_id || $signedRoles->contains('supervisor'));

        return [
            'created_by' => $this->createdBy,
            'knows_by' => $creatorSigned ? $this->knowsBy : null,
            'supervisor' => $knowsSigned ? $this->supervisor : null,
            'employee' => $supervisorDone ? $this->user : null,
        ];
    }
}
