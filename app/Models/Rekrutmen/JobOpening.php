<?php

namespace App\Models\Rekrutmen;

use App\Models\Master\JobPositions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lowongan di DB `rekrutmen_db`. Port dari App\Models\JobOpening project `rekrutmen`.
 *
 * BEDA dari aslinya: `booted()` yang mengisi created_by/updated_by/deleted_by lewat
 * auth()->id() DIHAPUS — id user HRIS ≠ id user `rekrutmen_db` (FK beda tabel). Lowongan
 * yang dibuat/diedit dari HRIS: kolom aktor itu dibiarkan null. Jejak "siapa" untuk
 * pergerakan kandidat tetap tercatat di job_application_status_logs.changed_by_name.
 */
class JobOpening extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'recruitment';

    protected $fillable = [
        'id', 'company_id', 'job_position_id', 'title', 'division_name', 'is_nakes', 'company_name', 'quota', 'max_applicants',
        'applications_deadline', 'hris_job_opening_id', 'is_active', 'description',
        'requirements', 'requires_psychotest',
        'published_at', 'closed_at', 'created_by_name', 'published_by', 'closed_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_nakes' => 'boolean',
        'quota' => 'integer',
        'max_applicants' => 'integer',
        'applications_deadline' => 'date',
        'requires_psychotest' => 'boolean',
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * division_name selalu diturunkan dari jabatan HRIS yang dipilih, tidak pernah diketik. Semua
     * rekrutmen dikelola dari HRIS dan jabatannya wajib dari master HRIS, jadi unitnya sudah pasti
     * ketahuan. Yang diisi nama DEPARTEMEN-nya, bukan divisi (keputusan user 2026-09-24) - lihat
     * JobPositions::getDepartmentNameAttribute(). Nama kolomnya tetap `division_name` karena itu
     * kolom di rekrutmen_db yang jadi token {divisi} pada naskah Persetujuan Onboarding; nilainya
     * dititipkan ke sana karena project `rekrutmen` tidak punya koneksi ke DB HRIS.
     *
     * Dipasang di hook simpan, BUKAN di afterStateUpdated form: kalau hanya di form, buka-simpan
     * lowongan lama tanpa menyentuh pilihan jabatan tidak akan mengisi divisinya. Di sini semua jalur
     * penulis ikut - form Filament HRIS maupun RecruitmentSync.
     */
    protected static function booted(): void
    {
        static::saving(function (self $opening) {
            if (! $opening->job_position_id) {
                return;
            }

            if (! $opening->isDirty('job_position_id') && $opening->division_name && $opening->exists) {
                return;
            }

            $position = JobPositions::find($opening->job_position_id);
            $opening->division_name = $position?->department_name ?? $opening->division_name;
            // Salinan is_nakes (project rekrutmen tak punya koneksi ke DB HRIS) - dipakai form Data Diri
            // kandidat untuk menampilkan field STR. Jabatan yang ditandai nakes belakangan: lihat
            // JobPositions::booted().
            $opening->is_nakes = (bool) $position?->is_nakes;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(JobOpeningStageLog::class)->orderBy('done_at');
    }

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'job_opening_quiz');
    }

    public function offlineTests(): BelongsToMany
    {
        return $this->belongsToMany(OfflineTest::class, 'job_opening_offline_test');
    }

    public function offlineTestSchedules(): HasMany
    {
        return $this->hasMany(OfflineTestSchedule::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(JobOpeningAgreement::class)->orderBy('sort_order');
    }

    public function offeringCompensationRows(): HasMany
    {
        return $this->hasMany(JobOpeningOfferingCompensationRow::class)->orderBy('sort_order');
    }

    /** Kompensasi mode LIST (RS) — type mitra | kontrak. Mode TABEL (SGG) pakai offeringCompensationRows. */
    public function offeringCompensationItems(): HasMany
    {
        return $this->hasMany(JobOpeningOfferingCompensationItem::class)->orderBy('sort_order');
    }

    /**
     * Daftar persetujuan efektif kandidat lowongan ini: persetujuan perusahaan, versi jabatan
     * menimpa yang judulnya sama, plus persetujuan khusus jabatan yang judulnya baru.
     * Port dari project `rekrutmen`.
     */
    public function effectiveAgreements(): \Illuminate\Support\Collection
    {
        $this->loadMissing('company.agreements', 'agreements');

        // Perusahaan tanpa persetujuan onboarding: tidak ada naskah efektif (selaras project rekrutmen).
        if ($this->company && ! $this->company->requires_agreement) {
            return collect();
        }

        $items = collect();
        foreach ($this->company?->agreements ?? collect() as $a) {
            $items[mb_strtolower(trim($a->title))] = [
                'title' => $a->title, 'content' => $a->content,
                'company_agreement_id' => $a->id, 'job_opening_agreement_id' => null,
            ];
        }
        foreach ($this->agreements as $a) {
            $items[mb_strtolower(trim($a->title))] = [
                'title' => $a->title, 'content' => $a->content,
                'company_agreement_id' => null, 'job_opening_agreement_id' => $a->id,
            ];
        }

        return $items->values();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Tahap terjauh yang sudah dicapai kandidat di lowongan ini, dihitung langsung dari data.
     * Port apa adanya dari project `rekrutmen`.
     *
     * @return array{label: string, color: string}
     */
    public function currentStage(): array
    {
        $counts = $this->applications()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        if ($counts->sum() === 0) {
            return ['label' => 'Belum Ada Pelamar', 'color' => 'gray'];
        }
        if (($counts['onboarding'] ?? 0) > 0) {
            return ['label' => 'TTD & Onboarding', 'color' => 'success'];
        }
        if (($counts['mcu'] ?? 0) > 0) {
            return ['label' => 'MCU', 'color' => 'warning'];
        }
        if (($counts['offering'] ?? 0) > 0) {
            return ['label' => 'Offering Letter', 'color' => 'warning'];
        }
        if (($counts['interview'] ?? 0) > 0) {
            return ['label' => 'Wawancara', 'color' => 'warning'];
        }
        if (($counts['shortlisted'] ?? 0) > 0) {
            if (! $this->quizzes()->exists()) {
                return ['label' => 'Tes: Kuis Belum Dipasang', 'color' => 'danger'];
            }
            $anyActivated = $this->applications()->where('status', 'shortlisted')->whereNotNull('quiz_sent_at')->exists();

            return $anyActivated
                ? ['label' => 'Tes: Berjalan', 'color' => 'info']
                : ['label' => 'Tes: Belum Diaktifkan', 'color' => 'warning'];
        }
        if (($counts['applied'] ?? 0) > 0 || ($counts['screening'] ?? 0) > 0) {
            return ['label' => 'Seleksi Berkas', 'color' => 'gray'];
        }
        if (($counts['passed'] ?? 0) > 0) {
            return ['label' => 'Selesai', 'color' => 'success'];
        }

        return ['label' => 'Tidak Ada Kandidat Aktif', 'color' => 'gray'];
    }
}
