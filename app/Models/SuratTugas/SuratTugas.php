<?php

namespace App\Models\SuratTugas;

use App\Contracts\Signable;
use App\Enum\DocumentType;
use App\Models\Concerns\HasDocumentSignatures;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Surat Perintah Tugas: atasan/direktur menugaskan satu atau lebih bawahan bekerja di luar kantor.
 * Penanda tangan tunggal (pemberi tugas), ditentukan dari master PenandatanganSuratTugas.
 * Nomor diketik manual (sama seperti SP/BA - belum ada generator nomor surat).
 */
class SuratTugas extends Model implements Signable
{
    use HasUlids;
    use HasDocumentSignatures;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_MENUNGGU_TTD = 'menunggu_ttd';

    public const STATUS_TERBIT = 'terbit';

    public const ROLE_PEMBERI_TUGAS = 'pemberi_tugas';

    protected $table = 'surat_tugas';

    protected $fillable = [
        'company_id', 'nomor', 'ttd_user_id', 'nama_ttd', 'jabatan_ttd',
        'tugas', 'tempat', 'transportasi', 'lain_lain', 'waktu', 'status', 'created_by', 'nip_ttd', 'payload_version',
    ];

    /** Isi surat (di-hash TTD) terkunci begitu keluar dari draft. */
    private const CONTENT_FIELDS = ['nomor', 'tugas', 'tempat', 'transportasi', 'lain_lain', 'waktu', 'nama_ttd', 'jabatan_ttd', 'nip_ttd', 'ttd_user_id', 'company_id'];

    protected static function booted(): void
    {
        static::updating(function (SuratTugas $surat) {
            if ($surat->getOriginal('status') !== self::STATUS_DRAFT && $surat->isDirty(self::CONTENT_FIELDS)) {
                throw new \LogicException('Surat Perintah Tugas sudah dikirim untuk ditandatangani, isinya tidak bisa diubah.');
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function ttdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ttd_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(SuratTugasPeserta::class, 'surat_tugas_id');
    }

    public function periode(): HasMany
    {
        return $this->hasMany(SuratTugasPeriode::class, 'surat_tugas_id')->orderBy('start_date');
    }

    public function isTerbit(): bool
    {
        return $this->status === self::STATUS_TERBIT;
    }

    /** "2 Agustus 2026 s/d 3 Agustus 2026, 5 Agustus 2026 s/d selesai" - tanggal selesai kosong = sampai selesai. */
    public function periodeLabel(): string
    {
        return $this->periode->map(fn ($p) => match (true) {
            $p->end_date === null => $p->start_date->translatedFormat('j F Y') . ' s/d selesai',
            $p->start_date->isSameDay($p->end_date) => $p->start_date->translatedFormat('j F Y'),
            default => $p->start_date->translatedFormat('j F Y') . ' s/d ' . $p->end_date->translatedFormat('j F Y'),
        })->implode(', ');
    }

    /**
     * Baris tanggal di cetakan surat, satu baris per periode:
     *   1 hari      -> "Senin, 28 Agustus 2026"
     *   rentang     -> "Senin - Rabu, 26 s.d. 28 Agustus 2026"
     *   lintas bulan-> "Jumat - Sabtu, 30 Agustus s.d. 2 September 2026"
     *   tanpa tanggal selesai -> sama dengan 1 hari: hanya tanggal mulai
     *
     * @return array<int, string>
     */
    public function periodeLinesSurat(): array
    {
        return $this->periode->map(function ($p) {
            $start = $p->start_date;
            $end = $p->end_date;

            return match (true) {
                $end === null || $start->isSameDay($end) => $start->translatedFormat('l, j F Y'),
                $start->isSameMonth($end) => $start->translatedFormat('l') . ' - ' . $end->translatedFormat('l') . ', '
                    . $start->translatedFormat('j') . ' s.d. ' . $end->translatedFormat('j F Y'),
                $start->isSameYear($end) => $start->translatedFormat('l') . ' - ' . $end->translatedFormat('l') . ', '
                    . $start->translatedFormat('j F') . ' s.d. ' . $end->translatedFormat('j F Y'),
                default => $start->translatedFormat('l') . ' - ' . $end->translatedFormat('l') . ', '
                    . $start->translatedFormat('j F Y') . ' s.d. ' . $end->translatedFormat('j F Y'),
            };
        })->all();
    }

    /** @return array<string, mixed> */
    public function signablePayload(): array
    {
        $this->loadMissing(['peserta', 'periode']);

        $payload = [
            'nomor' => $this->nomor,
            'tugas' => $this->tugas,
            'tempat' => $this->tempat,
            'transportasi' => $this->transportasi,
            'lain_lain' => $this->lain_lain,
            'waktu' => $this->waktu,
            'nama_ttd' => $this->nama_ttd,
            'jabatan_ttd' => $this->jabatan_ttd,
            'peserta' => $this->peserta->pluck('user_id')->sort()->values()->all(),
            'periode' => $this->periode->map(fn ($p) => [$p->start_date->toDateString(), $p->end_date?->toDateString()])->all(),
        ];

        // Versi 2: semua yang tercetak/tampil di surat ikut di-hash - perusahaan penerbit, akun dan
        // NIP penanda tangan, serta nama/NIP/jabatan tiap peserta (snapshot saat diajukan).
        // Surat versi 1 (ditandatangani sebelum ini) dihitung dengan bentuk lama supaya hash-nya
        // tetap cocok - JANGAN ubah key payload versi 1.
        if ($this->payload_version >= 2) {
            $payload['company_id'] = $this->company_id;
            $payload['ttd_user_id'] = $this->ttd_user_id;
            $payload['nip_ttd'] = $this->nip_ttd;
            $payload['peserta_identitas'] = $this->peserta
                ->sortBy('user_id')
                ->map(fn ($p) => [$p->user_id, $p->nama, $p->nip, $p->jabatan])
                ->values()
                ->all();
        }

        return $payload;
    }

    public function documentNumber(): string
    {
        return $this->nomor ?: 'ST-' . $this->id;
    }

    public function documentTypeLabel(): string
    {
        return 'Surat Perintah Tugas';
    }

    public function documentType(): DocumentType
    {
        return DocumentType::SURAT_TUGAS;
    }

    // Belum ada jalur pembatalan surat tugas - tambah kolom cancelled_at kalau kebutuhannya muncul.
    public function isRevoked(): bool
    {
        return false;
    }

    public function revokedReason(): ?string
    {
        return null;
    }

    public function expectedSignerRoles(): array
    {
        return [self::ROLE_PEMBERI_TUGAS => 'Pemberi Tugas'];
    }

    public function assignedSigners(): array
    {
        $this->loadMissing('ttdUser');

        return [self::ROLE_PEMBERI_TUGAS => $this->ttdUser];
    }

    public function signSummary(): array
    {
        $this->loadMissing(['peserta.user', 'periode']);

        return array_filter([
            'Nomor' => $this->nomor,
            'Ditugaskan' => $this->peserta->map(fn ($p) => $p->displayName())->filter()->implode(', '),
            'Tugas' => \Illuminate\Support\Str::limit(trim(strip_tags($this->tugas)), 160),
            'Tempat' => $this->tempat,
            'Tanggal' => $this->periodeLabel(),
        ]);
    }
}
