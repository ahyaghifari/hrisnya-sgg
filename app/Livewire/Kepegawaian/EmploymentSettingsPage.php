<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Enum\SettingTypeEnum;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\SuratTugas\PenandatanganSuratTugas;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Pengaturan Kepegawaian" - form per company untuk KepegawaianSettingEnum (max masa
 * kerja kontrak, durasi per perpanjangan, usia pensiun). Dipakai otomatis oleh
 * UserTransitionService buat hitung contract_end_date & guard usia pensiun. Layout niru pola
 * AttendanceSettingsPage (kiri daftar company, kanan form).
 */
#[Layout('components.layout', ['title' => 'Pengaturan Kepegawaian'])]
class EmploymentSettingsPage extends Component
{
    public ?string $companyId = null;

    /** @var array<string, string> KepegawaianSettingEnum value => value (company terpilih) */
    public array $values = [];

    public ?string $viewingAuditKey = null;

    /** Pemberi tugas / penanda tangan Surat Perintah Tugas (1 orang per company). */
    public ?string $signerUserId = null;

    public ?string $signerNama = null;

    public ?string $signerJabatan = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->loadCompanyState();
    }

    // Gerbang di selectCompany() saja tidak cukup - Livewire property bisa di-$set langsung
    // dari request yang di-craft (lewat hook updated*, bukan lewat method public), jadi
    // companyId perlu divalidasi ulang di sini juga.
    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
        $this->loadCompanyState();
    }

    private function loadCompanyState(): void
    {
        $signer = $this->companyId ? PenandatanganSuratTugas::where('company_id', $this->companyId)->first() : null;
        $this->signerUserId = $signer?->user_id;
        $this->signerNama = $signer?->nama;
        $this->signerJabatan = $signer?->jabatan;

        if (! $this->companyId) {
            $this->values = [];
            return;
        }

        $keys = collect(KepegawaianSettingEnum::cases())->map(fn ($e) => $e->value);

        $companyRows = Setting::where('company_id', $this->companyId)->whereIn('key', $keys)->pluck('value', 'key');
        $globalFallback = Setting::whereNull('company_id')->whereIn('key', $keys)->pluck('value', 'key');

        $this->values = collect(KepegawaianSettingEnum::cases())
            ->mapWithKeys(fn ($e) => [$e->value => (string) ($companyRows[$e->value] ?? $globalFallback[$e->value] ?? $e->default())])
            ->all();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function openAuditLog(string $key): void
    {
        $this->viewingAuditKey = $key;
    }

    public function closeAuditLog(): void
    {
        $this->viewingAuditKey = null;
    }

    public function getAuditLogsProperty()
    {
        if (! $this->viewingAuditKey) {
            return collect();
        }

        return AuditLog::where('company_id', $this->companyId)
            ->where('key', $this->viewingAuditKey)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /** PENGANGKATAN_DIBUKA sengaja dikeluarkan dari halaman ini - toggle-nya dipindah ke
     * Dashboard Sikartap (izin BUKA_TUTUP_PENGAJUAN_KARTAP), lihat DashboardSikartapPage.
     * Halaman ini cuma atur MODE-nya (select masa_kerja/buka_tutup), bukan status buka/tutup itu
     * sendiri.
     * @return KepegawaianSettingEnum[] */
    public function getSettingsProperty(): array
    {
        return array_values(array_filter(
            KepegawaianSettingEnum::cases(),
            fn (KepegawaianSettingEnum $case) => $case !== KepegawaianSettingEnum::PENGANGKATAN_DIBUKA,
        ));
    }

    public function getCanEditSettingsProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_KEPEGAWAIAN->value);
    }

    /** Calon pemberi tugas: karyawan company terpilih. */
    public function getSignerOptionsProperty()
    {
        return User::whereCompany($this->companyId)->orderByName()->get()->pluck('name', 'id');
    }

    /** Alasan buka blokir per user (key = user id). */
    public array $unlockReasons = [];

    public function getCanUnlockSigningProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::BUKA_BLOKIR_TANDA_TANGAN->value);
    }

    /** Akun di perusahaan terpilih yang tanda tangannya sedang diblokir (password salah berulang). */
    public function getLockedSignersProperty()
    {
        return $this->canUnlockSigning
            ? app(\App\Services\Document\SigningLockService::class)->lockedUsers($this->companyId)
            : collect();
    }

    public function unlockSigning(string $userId): void
    {
        abort_unless($this->canUnlockSigning, 403);

        $reason = trim($this->unlockReasons[$userId] ?? '');

        if ($reason === '') {
            $this->addError("unlockReasons.$userId", 'Alasan wajib diisi.');

            return;
        }

        // Otorisasi lengkap (bukan akun sendiri, satu perusahaan) ada di SigningLockService::unlock().
        app(\App\Services\Document\SigningLockService::class)->unlock(User::findOrFail($userId), Auth::user(), $reason);

        unset($this->unlockReasons[$userId]);

        Notification::make()->title('Blokir tanda tangan dibuka')->success()->send();
    }

    public function saveSigner(): void
    {
        abort_unless($this->canEditSettings, 403);

        $this->validate([
            'signerUserId' => 'required',
            'signerNama' => 'required|string|max:255',
            'signerJabatan' => 'required|string|max:255',
        ], [
            'signerUserId.required' => 'Pilih akun penanda tangan.',
            'signerNama.required' => 'Nama di surat wajib diisi.',
            'signerJabatan.required' => 'Jabatan di surat wajib diisi.',
        ]);

        // Hanya karyawan company yang sedang dikelola (Livewire property bisa di-$set dari luar).
        abort_unless($this->signerOptions->has($this->signerUserId), 422);

        PenandatanganSuratTugas::updateOrCreate(
            ['company_id' => $this->companyId],
            ['user_id' => $this->signerUserId, 'nama' => $this->signerNama, 'jabatan' => $this->signerJabatan],
        );

        Notification::make()->title('Penanda tangan Surat Perintah Tugas disimpan')->success()->send();
    }

    public function save(): void
    {
        abort_unless($this->canEditSettings, 403);

        if (! $this->companyId) {
            return;
        }

        foreach ($this->values as $key => $value) {
            // PENGANGKATAN_DIBUKA tidak dirender di halaman ini (lihat getSettingsProperty) tapi
            // masih ada di $values (dimuat loadCompanyState() dari semua case) - jangan sampai
            // ikut ditulis ulang dari sini kalau suatu saat direktori $values dipakai penuh.
            if ($key === KepegawaianSettingEnum::PENGANGKATAN_DIBUKA->value) {
                continue;
            }

            $case = KepegawaianSettingEnum::from($key);
            // Checkbox uncheck tidak terkirim - normalisasi ke 0 (pola PembinaanSettingsPage).
            $normalized = $case->type() === SettingTypeEnum::BOOLEAN ? ($value == '1' ? '1' : '0') : $value;

            Setting::updateOrCreate(
                ['company_id' => $this->companyId, 'key' => $key],
                [
                    'module' => 'kepegawaian',
                    'type' => $case->type()->value,
                    'label' => $case->label(),
                    'description' => $case->helperText(),
                    'value' => $normalized,
                ]
            );
        }

        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'perusahaan ini';

        Notification::make()
            ->title("Pengaturan Kepegawaian {$companyName} berhasil disimpan")
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.kepegawaian.employment-settings-page');
    }
}
