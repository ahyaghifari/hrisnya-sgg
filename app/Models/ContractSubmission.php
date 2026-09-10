<?php

namespace App\Models;

use App\Models\Master\Companies;
use App\Models\User\UserContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1 pengajuan perpanjangan kontrak (self-service). Karyawan kategori Kontrak mengajukan +
 * unggah dokumen (daftar dinamis dari Setting `contract_extension_documents`), lalu penilai
 * (resolve dari `sikartap_reviewers` context `contract`, lihat SikartapReviewerResolver)
 * memutuskan `perpanjang` atau `tidak_perpanjang` (tanpa durasi). **Tidak lewat Approval Engine.**
 *
 * Status: draft -> diajukan -> dinilai -> selesai. (ditolak = tidak_perpanjang, dibatalkan =
 * pengaju batal.) `perpanjang` -> status `dinilai`; HR menetapkan durasi (decision_durasi_bulan)
 * -> bikin 1 baris user_contracts baru (new_contract_id) + status `selesai`. Kontrak lama
 * otomatis jadi riwayat (ledger append-only, "aktif" = currentContract() terbaru yang sudah mulai).
 */
class ContractSubmission extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'user_contract_id',
        'company_id',
        'status',
        'alasan',
        'decision',
        'decision_durasi_bulan',
        'decision_note',
        'decided_by',
        'decided_at',
        'new_contract_id',
        'submitted_at',
    ];

    protected $casts = [
        'decision_durasi_bulan' => 'integer',
        'decided_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kontrak berjalan yang diperpanjang lewat pengajuan ini (induk). */
    public function userContract(): BelongsTo
    {
        return $this->belongsTo(UserContract::class, 'user_contract_id');
    }

    /** Baris user_contracts hasil perpanjangan (kalau decision `perpanjang`). */
    public function newContract(): BelongsTo
    {
        return $this->belongsTo(UserContract::class, 'new_contract_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractSubmissionDocument::class);
    }
}
