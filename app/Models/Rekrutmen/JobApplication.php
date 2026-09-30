<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Lamaran di DB `rekrutmen_db` — record inti seluruh alur rekrutmen. Port dari project
 * `rekrutmen` (App\Models\JobApplication) termasuk state machine `booted()`.
 *
 * BEDA dari aslinya: `logStatusChange()` menulis `changed_by = null` + `changed_by_name`
 * (nama aktor HRIS, snapshot) — id user HRIS ≠ id user `rekrutmen_db`. App `rekrutmen`
 * sendiri tetap menulis `changed_by` FK saat aksi jalan di sana. Lihat migration
 * add_changed_by_name_to_job_application_status_logs di repo `rekrutmen` (2026-09-04).
 */
class JobApplication extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'recruitment';

    protected $fillable = [
        'user_id', 'job_opening_id', 'status', 'quiz_sent_at', 'quiz_result',
        'psychotest_completed_at', 'interview_result', 'interview_notes', 'offering_letter_path',
        'offering_letter_uploaded_at', 'offering_letter_signed_path', 'offering_letter_signed_at',
        'offering_signed_reviewed_at', 'offering_signed_revision_reason', 'onboarding_date',
    ];

    protected $casts = [
        'quiz_sent_at' => 'datetime',
        'psychotest_completed_at' => 'datetime',
        'offering_letter_uploaded_at' => 'datetime',
        'offering_letter_signed_at' => 'datetime',
        'offering_signed_reviewed_at' => 'datetime',
        'onboarding_date' => 'date',
    ];

    public const STATUS_APPLIED = 'applied';
    public const STATUS_SCREENING = 'screening';
    public const STATUS_SHORTLISTED = 'shortlisted';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_OFFERING = 'offering';
    public const STATUS_MCU = 'mcu';
    public const STATUS_ONBOARDING = 'onboarding';
    public const STATUS_PASSED = 'passed';
    public const STATUS_REJECTED = 'rejected';

    public const ACTIVE_STATUSES = [self::STATUS_APPLIED, self::STATUS_SCREENING, self::STATUS_INTERVIEW];

    protected static function booted(): void
    {
        static::created(fn (self $app) => $app->logStatusChange(null, $app->status));

        static::updated(function (self $app) {
            if (! $app->wasChanged('status')) {
                return;
            }

            $app->logStatusChange($app->getOriginal('status'), $app->status);

            if (in_array($app->status, [self::STATUS_ONBOARDING, self::STATUS_PASSED], true)) {
                $candidate = Candidate::firstOrCreate(
                    ['user_id' => $app->user_id],
                    ['job_application_id' => $app->id, 'name' => $app->answersMap()['full_name'] ?? $app->user?->name],
                );
                if ($candidate->job_application_id !== $app->id) {
                    $candidate->update(['job_application_id' => $app->id]);
                }
            }

            if ($app->status === self::STATUS_PASSED) {
                $app->closeJobOpeningIfQuotaFilled();
            }
        });
    }

    private function closeJobOpeningIfQuotaFilled(): void
    {
        $opening = $this->jobOpening;
        if (! $opening || ! $opening->is_active || $opening->quota <= 0) {
            return;
        }

        $passedCount = self::where('job_opening_id', $opening->id)->where('status', self::STATUS_PASSED)->count();
        if ($passedCount >= $opening->quota) {
            $opening->update(['is_active' => false]);
        }
    }

    private function logStatusChange(?string $from, string $to): void
    {
        JobApplicationStatusLog::create([
            'job_application_id' => $this->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => null,
            'changed_by_name' => Auth::user()?->name ?? 'Sistem',
            'changed_at' => now(),
        ]);
    }

    /** Catat milestone yang TIDAK mengubah kolom status (mis. hasil tes lulus) agar tetap di Riwayat. */
    public function logMilestone(string $milestone): void
    {
        $this->logStatusChange($this->status, $milestone);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(JobApplicationStatusLog::class)->orderBy('changed_at');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(JobApplicationAnswer::class);
    }

    public function candidate(): HasOne
    {
        return $this->hasOne(Candidate::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function offlineTestResults(): HasMany
    {
        return $this->hasMany(OfflineTestResult::class);
    }

    public function offlineTestInvitees(): HasMany
    {
        return $this->hasMany(OfflineTestInvitee::class);
    }

    public function offeringNegotiations(): HasMany
    {
        return $this->hasMany(OfferingNegotiation::class)->orderBy('created_at');
    }

    public function interviewInvitees(): HasMany
    {
        return $this->hasMany(InterviewInvitee::class);
    }

    /**
     * Undangan wawancara TERBARU. Dulu hasOne polos karena wawancara cuma sekali; sejak wawancara
     * boleh dijadwalkan berkali-kali (2026-09-28) satu pelamar bisa punya beberapa undangan, jadi
     * yang diambil yang terakhir - itu sesi yang sedang berjalan untuk dia. Pemanggil lama (mis.
     * tombol Ubah Jadwal per orang) otomatis mengenai sesi terakhir, yang memang yang dimaksud.
     *
     * Keputusan lulus/tidak TIDAK ada di sini: dia menempel di interview_result + status lamaran
     * ini, lihat WawancaraPage::tandaiHasilWawancaraAction().
     */
    public function interviewInvitee(): HasOne
    {
        return $this->hasOne(InterviewInvitee::class)->latestOfMany();
    }

    /** Undangan sesi tertentu, null kalau belum pernah dijadwalkan di sesi itu. */
    public function interviewInviteeForRound(int $round): ?InterviewInvitee
    {
        return $this->interviewInvitees->first(fn (InterviewInvitee $inv) => $inv->round() === $round);
    }

    public function mcuInvitee(): HasOne
    {
        return $this->hasOne(McuInvitee::class);
    }

    /** @return array<string,mixed> field_key => value */
    public function answersMap(): array
    {
        return $this->answers->pluck('value', 'field_key')->all();
    }

    /**
     * Cek NIK pelamar (dari akun rekrutmen, wajib diisi saat registrasi) terhadap
     * `user_profiles.no_nik` HRIS — TERMASUK karyawan yang sudah di-soft-delete
     * (withTrashed) — buat ketahuan kalau pelamar ini pernah/sedang jadi karyawan di
     * salah satu company. Kosong kalau NIK belum ada atau tidak match siapa pun.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    public function formerEmployeeMatches(): \Illuminate\Support\Collection
    {
        $nik = $this->user?->nik;

        if (! $nik) {
            return collect();
        }

        return \App\Models\User::withTrashed()
            ->whereHas('profile', fn ($q) => $q->where('no_nik', $nik))
            ->with(['company', 'profile.jobPosition'])
            ->get();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Render PDF offering letter dari template company + kompensasi lowongan + data kandidat
     * yang sudah dikonfirmasi (offering_full_name dkk). DIOLAH LANGSUNG saat diminta (dipanggil
     * dari CandidateDocumentProxyController::offeringLetter()) — tidak disimpan, jadi selalu
     * pakai data & config TERBARU, tidak ada "generate ulang" manual. Background & tanda tangan
     * ditempel sebagai data URI base64 (bukan path file, hindari batasan chroot dompdf) —
     * background dari disk `rekrutmen` (privat), tanda tangan dari disk `rekrutmen_public`
     * (root beda, root fisiknya folder `public` sebelah punya `rekrutmen` — lihat
     * config/filesystems.php) — keduanya dibaca LANGSUNG dari disk, satu server, tidak lewat
     * HTTP/symlink publik (rekrutmen sendiri belum punya `storage:link`).
     */
    public function renderOfferingLetterPdf(): \Barryvdh\DomPDF\PDF
    {
        $company = $this->jobOpening->company;

        // Mode tabel (SGG): nominal angka di-auto-convert jadi "Rp X.XXX.XXX" — kolomnya
        // memang selalu diisi nominal. Mode list (RS): tampil apa adanya (bisa range/teks
        // bebas/kosong) — daftarnya sudah dipisah Orientasi/Mitra vs Kontrak di templatenya.
        $isTableMode = $company->offering_letter_template === \App\Enum\Recruitment\OfferingLetterTemplate::Sgg;
        $formatAmount = function (?string $value) use ($isTableMode) {
            if (blank($value)) {
                return null;
            }

            return $isTableMode && is_numeric($value)
                ? 'Rp' . number_format((float) $value, 0, ',', '.')
                : $value;
        };

        $compensationRows = $this->jobOpening->offeringCompensationRows->map(fn ($row) => [
            'label' => $row->label,
            'orientasi' => $formatAmount($row->amount_orientasi),
            'kontrak' => $formatAmount($row->amount_kontrak),
        ])->all();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView($company->offering_letter_template->view(), [
            'background_path' => $this->resolveOfferingBackgroundDataUri($company),
            'full_name' => $this->offering_full_name,
            'birth_place' => $this->offering_birth_place,
            'birth_date' => \Illuminate\Support\Carbon::parse($this->offering_birth_date)->translatedFormat('d F Y'),
            'domicile_address' => $this->offering_domicile_address,
            'jabatan' => $this->jobOpening->title,
            'lokasi_kerja' => $company->name,
            'tanggal_surat' => now()->translatedFormat('d F Y'),
            'compensation_rows' => $compensationRows,
            'benefits' => $company->offeringBenefits->map(fn ($b) => ['label' => $b->label, 'is_starred' => $b->is_starred])->all(),
            'benefit_note' => $company->offering_benefit_note,
            'terms' => $company->offeringTerms->pluck('label')->all(),
            'signatory_name' => $company->offering_signatory_name ?? '-',
            'signatory_title' => $company->offering_signatory_title ?? '-',
            'signatory_signature_path' => $this->resolveOfferingSignatureDataUri($company),
        ]);
    }

    private function resolveOfferingBackgroundDataUri(Company $company): ?string
    {
        $path = $company->offering_letter_background_path ?: 'offering-letter-bg/default.jpg';
        $disk = \Illuminate\Support\Facades\Storage::disk('rekrutmen');

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:' . ($disk->mimeType($path) ?: 'image/jpeg') . ';base64,' . base64_encode($disk->get($path));
    }

    private function resolveOfferingSignatureDataUri(Company $company): ?string
    {
        $path = $company->offering_signatory_signature_path;

        if (! $path) {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('rekrutmen_public');

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:' . ($disk->mimeType($path) ?: 'image/png') . ';base64,' . base64_encode($disk->get($path));
    }
}
