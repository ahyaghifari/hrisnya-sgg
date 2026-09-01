<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Exports\Payroll\PayrollSlipRekapExport;
use App\Imports\Payroll\BonDetailImport;
use App\Imports\Payroll\BonImport;
use App\Imports\Payroll\PayrollRevisionImport;
use App\Jobs\BuildPayrollSlipZipJob;
use App\Jobs\SendPayrollSlipJob;
use App\Livewire\Keuangan\Concerns\HasSlipSendStatus;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Notifications\PayrollPaid;
use App\Services\Payroll\PayrollService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Halaman hasil Generate Payroll — dibangun custom (BUKAN reuse Filament Table) atas
 * permintaan eksplisit user, beda dari SalaryComponentsPage/IncentivesPage/dst di modul ini.
 * Filter perusahaan + periode, tombol "Lihat" ke halaman detail (PayrollDetailPage). Tombol
 * header (Import Bon) muncul kondisional sesuai konfigurasi company yang lagi dipilih — bukan
 * statis buat semua company, dan buka modal (bukan navigasi ke halaman lain) atas permintaan
 * user. Import Insentif SUDAH DIHAPUS (lihat docblock IncentiveCalculator). Input manual per
 * baris (bukan import) cuma ada di halaman detail payroll (PayrollDetailPage), tidak di sini.
 * Cari/company/period semuanya lewat Livewire
 * (server-side) — SEBELUMNYA cari pakai List.js di browser, tapi List.js kedapatan mengunci
 * baris tbody-nya sendiri sehingga tidak ikut ke-morph Livewire begitu company/periode ganti
 * (baris karyawan lama nyangkut terus), jadi dicabut.
 */
#[Layout('components.layout', ['title' => 'Payroll'])]
class PayrollsPage extends Component
{
    use HasSlipSendStatus;
    use WithFileUploads;

    public ?string $companyId = null;
    public ?string $periodId = null;
    public string $search = '';

    public array $selected = [];

    public bool $showImportModal = false;
    public ?string $importType = null; // 'bon' - satu-satunya import yang tersisa (Insentif import sudah dihapus)
    public ?string $importPeriodId = null;
    public $importFile;
    public array $importErrors = [];
    public ?int $importSuccessCount = null;

    /** Baris hasil parsing file, sebelum disimpan — lihat previewImport()/confirmImport(). */
    public array $importPreviewRows = [];
    public ?string $importStoredPath = null;

    // ── Import Revisi (baca ulang hasil Export Excel yang sudah direvisi finance) ──
    public bool $showRevisionModal = false;
    public $revisionFile;
    public ?string $revisionStoredPath = null;
    /** @var array<int, array{payroll_id: string, nip: string, nama: string, label: string, component: string, old: float, new: float}> */
    public array $revisionDiffs = [];
    public array $revisionErrors = [];
    public ?int $revisionAppliedCount = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', Payroll::class), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    /** Ganti company -> pilihan periode lama tidak relevan lagi. */
    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->periodId = null;
        $this->selected = [];
    }

    public function updatedPeriodId(): void
    {
        $this->selected = [];
    }

    public function toggleSelectAll(bool $checked): void
    {
        $this->selected = $checked ? $this->payrolls->pluck('id')->all() : [];
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getPeriodsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return PayrollPeriod::where('company_id', $this->companyId)->orderByDesc('start_date')->get();
    }

    /** Payroll::query() sudah di-scope company + periode — dipakai getPayrollsProperty & HasSlipSendStatus. */
    protected function baseSlipQuery(): \Illuminate\Contracts\Database\Eloquent\Builder
    {
        return Payroll::query()
            ->when($this->companyId, fn ($q) => $q->whereHas('payrollPeriod', fn ($q2) => $q2->where('company_id', $this->companyId)))
            ->when($this->periodId, fn ($q) => $q->where('payroll_period_id', $this->periodId));
    }

    /** Cari nama/NIP — server-side (bukan List.js, lihat catatan kelas ini). */
    public function getPayrollsProperty()
    {
        $search = trim($this->search);

        // Ambil dulu SEMUA baris mentah (query di atas tidak menyentuh nominal terenkripsi
        // sama sekali - PayrollEncrypted baru jalan pas atribut diakses, bukan pas ->get()).
        $payrolls = $this->applySlipStatusFilter($this->baseSlipQuery())
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('nip', 'like', "%{$search}%")))
            ->with(['user', 'payrollPeriod'])
            ->orderByDesc('created_at')
            ->get();

        // BARU di sini, langkah terpisah (bukan di-chain sama fetch di atas), tiap baris
        // di-decrypt satu-satu dengan try/catch - 1 baris yang datanya rusak (plaintext lama
        // atau key beda) tidak lagi bikin SELURUH halaman crash (ViewException), cuma baris itu
        // yang tampil "Gagal dekripsi".
        foreach ($payrolls as $payroll) {
            $payroll->decryptFailed = false;

            foreach (['gross_salary' => 'safeGross', 'total_deductions' => 'safeDeductions', 'net_salary' => 'safeNet'] as $field => $safeKey) {
                try {
                    $payroll->{$safeKey} = $payroll->{$field};
                } catch (\Throwable) {
                    $payroll->{$safeKey} = null;
                    $payroll->decryptFailed = true;
                }
            }
        }

        return $payrolls;
    }

    /** Rekap Excel (format finance, lihat PayrollSlipRekapExport) - butuh 1 periode spesifik
     *  dipilih, bukan gabungan periode (biar kolom "PERIODE GAJI" & pengelompokan divisi masuk
     *  akal, satu file = satu bulan gajian seperti kebiasaan finance sekarang). */
    public function exportExcel()
    {
        abort_unless($this->periodId, 400, 'Pilih periode dulu sebelum export.');

        $payrolls = Payroll::where('payroll_period_id', $this->periodId)
            ->with([
                'user.profile.jobPosition',
                'user.profile.pangkat.pangkatSalary',
                'user.profile.pangkat.startKelas',
                'user.profile.pangkat.maxKelas',
                'user.profile.kelas',
                'user.profile.employeeType',
                'user.profile.orgStructure.parent.parent',
                'payrollPeriod',
                'items',
            ])
            ->get();

        $period = PayrollPeriod::find($this->periodId);
        $filename = 'rekap-gaji-' . str($period?->company?->name . '-' . $period?->start_date?->format('F-Y'))->slug() . '.xlsx';

        return Excel::download(new PayrollSlipRekapExport($payrolls, $period->company_id), $filename);
    }

    /**
     * Status ZIP slip gaji periode terpilih: 'none' (belum ada / belum pernah disiapkan),
     * 'building' (job queue lagi jalan), 'ready' (file siap diunduh). readyAt cuma ada saat 'ready'.
     *
     * @return array{status: string, readyAt?: string}
     */
    public function getSlipZipStateProperty(): array
    {
        if (! $this->periodId) {
            return ['status' => 'none'];
        }

        if (Cache::get("slipzip-building:{$this->periodId}")) {
            return ['status' => 'building'];
        }

        $disk = Storage::disk('local');
        $rel = app(PayrollService::class)->slipZipRelPath($this->periodId);

        if ($disk->exists($rel)) {
            return [
                'status' => 'ready',
                'readyAt' => \Carbon\Carbon::createFromTimestamp($disk->lastModified($rel))->translatedFormat('d M Y H:i'),
            ];
        }

        return ['status' => 'none'];
    }

    /** Dispatch job pembangun ZIP slip gaji (PDF ter-password per orang, lihat
     *  PayrollService::buildSlipZipToStorage) untuk payroll SUDAH DIBAYAR di periode terpilih.
     *  Berat — via queue, bukan langsung di request. */
    public function prepareSlipsZip(): void
    {
        abort_unless($this->periodId, 400, 'Pilih periode dulu sebelum menyiapkan slip.');
        abort_unless(Auth::user()->can('viewAny', Payroll::class), 403);

        $period = PayrollPeriod::findOrFail($this->periodId);

        if (! Payroll::where('payroll_period_id', $period->id)->where('is_paid', true)->exists()) {
            Notification::make()
                ->title('Belum ada payroll yang sudah dibayar di periode ini')
                ->warning()
                ->send();

            return;
        }

        Cache::put("slipzip-building:{$this->periodId}", true, now()->addHour());
        BuildPayrollSlipZipJob::dispatch($this->periodId, Auth::id());

        Notification::make()
            ->title('ZIP slip gaji sedang disiapkan')
            ->body('Diproses di background — tombol unduh muncul otomatis begitu selesai.')
            ->success()
            ->send();
    }

    public function downloadSlipsZip()
    {
        abort_unless($this->periodId, 400, 'Pilih periode dulu sebelum unduh slip.');
        abort_unless(Auth::user()->can('viewAny', Payroll::class), 403);

        $period = PayrollPeriod::with('company')->findOrFail($this->periodId);

        if (! Storage::disk('local')->exists(app(PayrollService::class)->slipZipRelPath($period->id))) {
            Notification::make()->title('File ZIP belum siap')->warning()->send();

            return;
        }

        return app(PayrollService::class)->downloadStoredSlipZip($period);
    }

    // ── Import Revisi ────────────────────────────────────────────────────────

    public function openRevisionModal(): void
    {
        abort_unless($this->canUpdatePayroll, 403);
        abort_unless($this->periodId, 400, 'Pilih periode dulu sebelum import revisi.');

        $this->revisionFile = null;
        $this->revisionDiffs = [];
        $this->revisionErrors = [];
        $this->revisionAppliedCount = null;
        $this->discardStoredRevisionFile();
        $this->resetErrorBag();
        $this->showRevisionModal = true;
    }

    public function closeRevisionModal(): void
    {
        $this->showRevisionModal = false;
        $this->discardStoredRevisionFile();
    }

    /** File dipilih (wire:model) -> langsung parse & tampilkan pratinjau perbedaan, belum
     *  ditulis ke database sama sekali - lihat PayrollRevisionImport (cuma baca, tidak nulis). */
    public function updatedRevisionFile(): void
    {
        $this->revisionErrors = [];
        $this->revisionDiffs = [];
        $this->revisionAppliedCount = null;

        $this->validate([
            'revisionFile' => 'required|file|mimes:xlsx,xls,csv',
        ], [], ['revisionFile' => 'file Excel']);

        $this->discardStoredRevisionFile();
        $this->revisionStoredPath = $this->revisionFile->store('temp-payroll-revisions');

        $period = PayrollPeriod::findOrFail($this->periodId);
        $import = new PayrollRevisionImport($period);
        Excel::import($import, Storage::path($this->revisionStoredPath));

        $this->revisionErrors = $import->getErrors();
        $this->revisionDiffs = $import->getDiffs();
    }

    /** Tombol "Terapkan Perubahan" di pratinjau - baru di sini nominalnya benar-benar ditulis,
     *  1 baris diff = 1 pemanggilan PayrollService::applyManualRevision() (regenerate TIDAK
     *  dipanggil di sini, karena itu akan menimpa balik revisi manual ini - lihat docblock
     *  method-nya). Payroll yang keburu dibayar SELAMA jeda preview->konfirmasi dilewati diam-diam. */
    public function confirmRevisionImport(): void
    {
        abort_unless($this->canUpdatePayroll, 403);

        if ($this->revisionDiffs === []) {
            return;
        }

        $payrollService = app(PayrollService::class);
        $applied = 0;

        foreach ($this->revisionDiffs as $diff) {
            $payroll = Payroll::with('payrollPeriod', 'user')->find($diff['payroll_id']);
            if (! $payroll || $payroll->is_paid) {
                continue;
            }

            $payrollService->applyManualRevision(
                $payroll,
                PayrollComponentEnum::from($diff['component']),
                (float) $diff['new'],
                'Import Revisi Excel',
                Auth::id(),
            );
            $applied++;
        }

        $this->revisionDiffs = [];
        $this->revisionAppliedCount = $applied;
        $this->discardStoredRevisionFile();
        $this->reset('revisionFile');
        $this->showRevisionModal = false;

        Notification::make()
            ->title("{$applied} perubahan diterapkan")
            ->success()
            ->send();
    }

    private function discardStoredRevisionFile(): void
    {
        if ($this->revisionStoredPath !== null) {
            Storage::delete($this->revisionStoredPath);
            $this->revisionStoredPath = null;
        }
    }

    /** Tombol Import Bon cuma tampil kalau komponen Bon aktif buat company ini. */
    public function getBonAktifProperty(): bool
    {
        if (! $this->companyId) {
            return false;
        }

        return CompanyPayrollComponent::query()
            ->where('company_id', $this->companyId)
            ->where('component', PayrollComponentEnum::BON->value)
            ->where('is_active', true)
            ->exists();
    }

    /** Import Bon cuma buat mode "Simple" (1 baris) - mode "Detail" (rincian satu-satu) HARUS
     *  lewat halaman detail payroll manual, supaya tiap baris punya keterangan sendiri dan tidak
     *  ada risiko import menimpa/menduplikasi rincian yang sudah diinput manual. */
    public function getBonMetodeProperty(): string
    {
        return (string) Setting::resolve('bon_metode', $this->companyId, default: 'simple');
    }

    /** Cuma periode yang sudah digenerate (processed_at) yang boleh ditempeli import. */
    public function getImportPeriodsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return PayrollPeriod::query()
            ->where('company_id', $this->companyId)
            ->whereNotNull('processed_at')
            ->orderByDesc('start_date')
            ->get();
    }

    public function getCanImportPayrollProperty(): bool
    {
        return Auth::user()->can('create', Payroll::class);
    }

    /**
     * Ability class-level (bukan cek instance tertentu) — pakai permission Shield mentah
     * ('Update:Payroll') langsung, sama seperti yang dicek PayrollPolicy::update() untuk
     * satu payroll, karena Gate::can('update', Payroll::class) tanpa instance tidak bisa
     * dipakai (policy method-nya wajib parameter $payroll).
     */
    public function getCanUpdatePayrollProperty(): bool
    {
        return Auth::user()->can('Update:Payroll');
    }

    /** Tombol "Kirim Slip Gaji ke Email" cuma tampil kalau SEMUA baris yang dicentang sudah dibayar. */
    public function getSelectedAllPaidProperty(): bool
    {
        if ($this->selected === []) {
            return false;
        }

        return ! Payroll::whereIn('id', $this->selected)->where('is_paid', false)->exists();
    }

    // ── Ceklis massal: tandai dibayar & kirim slip ke email ─────────────────

    public function markSelectedAsPaid(): void
    {
        abort_unless($this->canUpdatePayroll, 403);

        // Ambil dulu SEBELUM update masal — butuh tahu persis baris mana yang benar-benar
        // berubah dari belum ke sudah dibayar, biar tidak notifikasi ulang yang sudah dibayar
        // sebelumnya (bisa ikut kecentang tapi tidak seharusnya dianggap "baru dibayar").
        $newlyPaid = Payroll::whereIn('id', $this->selected)->where('is_paid', false)->with(['user', 'payrollPeriod'])->get();

        Payroll::whereIn('id', $newlyPaid->pluck('id'))->update(['is_paid' => true]);

        foreach ($newlyPaid as $payroll) {
            $payroll->user?->notify(new PayrollPaid($payroll));
        }

        $count = $newlyPaid->count();
        $this->selected = [];

        Notification::make()
            ->title("{$count} payroll ditandai sudah dibayar")
            ->success()
            ->send();
    }

    /** Dispatch job per karyawan (queue) — karyawan tanpa email dilewati, bukan bikin batch gagal semua. */
    public function sendSlipsToSelected(): void
    {
        abort_unless($this->canUpdatePayroll, 403);
        abort_unless($this->selectedAllPaid, 403, 'Semua payroll yang dipilih harus sudah dibayar dulu.');

        $payrolls = Payroll::whereIn('id', $this->selected)->with('user.profile')->get();
        $skipped = 0;

        foreach ($payrolls as $payroll) {
            // Sama fallback dengan SendPayrollSlipJob - email_pribadi diprioritaskan, baru email akun.
            if (! ($payroll->user?->profile?->email_pribadi ?: $payroll->user?->email)) {
                $skipped++;

                continue;
            }

            SendPayrollSlipJob::dispatch($payroll->id);
        }

        $sentCount = $payrolls->count() - $skipped;
        $this->selected = [];

        Notification::make()
            ->title("{$sentCount} slip gaji sedang dikirim")
            ->body($skipped > 0 ? "{$skipped} dilewati karena karyawan belum punya email." : 'Diproses via queue — email akan terkirim dalam beberapa saat.')
            ->success()
            ->send();
    }

    // ── Aksi per baris (single) ────────────────────────────────────────────

    public function markAsPaid(string $payrollId): void
    {
        abort_unless($this->canUpdatePayroll, 403);

        $payroll = Payroll::where('id', $payrollId)->where('is_paid', false)
            ->with(['user', 'payrollPeriod'])->first();

        if (! $payroll) {
            return;
        }

        $payroll->update(['is_paid' => true]);
        $payroll->user?->notify(new PayrollPaid($payroll));

        Notification::make()->title('Payroll ditandai sudah dibayar')->success()->send();
    }

    public function sendSlip(string $payrollId): void
    {
        abort_unless($this->canUpdatePayroll, 403);

        $payroll = Payroll::where('id', $payrollId)->where('is_paid', true)->with('user.profile')->first();
        abort_unless($payroll, 403, 'Payroll harus sudah dibayar dulu.');

        if (! ($payroll->user?->profile?->email_pribadi ?: $payroll->user?->email)) {
            Notification::make()->title('Karyawan belum punya email')->warning()->send();

            return;
        }

        SendPayrollSlipJob::dispatch($payroll->id);
        Notification::make()->title('Slip gaji sedang dikirim')->success()->send();
    }

    public function openImportModal(string $type): void
    {
        abort_unless(Auth::user()->can('create', Payroll::class), 403);

        $this->importType = $type;
        $this->importPeriodId = null;
        $this->importFile = null;
        $this->importErrors = [];
        $this->importSuccessCount = null;
        $this->importPreviewRows = [];
        $this->discardStoredImportFile();
        $this->resetErrorBag();
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->discardStoredImportFile();
    }

    /** Periode ganti setelah file sudah dipratinjau -> pratinjau lama tidak valid lagi, ulang. */
    public function updatedImportPeriodId(): void
    {
        if ($this->importStoredPath !== null) {
            $this->previewImport();
        }
    }

    /** File baru dipilih (wire:model) -> langsung parse & tampilkan pratinjau, JANGAN simpan dulu. */
    public function updatedImportFile(): void
    {
        $this->importErrors = [];
        $this->importSuccessCount = null;
        $this->previewImport();
    }

    private function previewImport(): void
    {
        abort_unless(Auth::user()->can('create', Payroll::class), 403);

        $this->importPreviewRows = [];
        $this->discardStoredImportFile();

        $this->validate([
            'importPeriodId' => 'required',
            'importFile' => 'required|file|mimes:xlsx,xls,csv',
        ], [], [
            'importPeriodId' => 'periode payroll',
            'importFile' => 'file Excel',
        ]);

        if (! $this->importGuardPasses()) {
            return;
        }

        $this->importStoredPath = $this->importFile->store('temp-payroll-imports');
        $import = $this->makeImport(preview: true);
        Excel::import($import, Storage::path($this->importStoredPath));

        $this->importPreviewRows = $import->getPreview();
    }

    /** Tombol "Simpan" di pratinjau -> baru di sini datanya benar-benar ditulis ke database. */
    public function confirmImport(): void
    {
        abort_unless(Auth::user()->can('create', Payroll::class), 403);

        if ($this->importStoredPath === null || ! $this->importGuardPasses()) {
            return;
        }

        $import = $this->makeImport(preview: false);

        try {
            Excel::import($import, Storage::path($this->importStoredPath));
        } finally {
            $this->discardStoredImportFile();
        }

        $this->importErrors = $import->getErrors();
        $this->importSuccessCount = $import->getSuccessCount();
        $this->importPreviewRows = [];
        $this->reset('importFile');

        // Mode Bon "Simple" (satu-satunya yang boleh lewat import Excel, lihat
        // importGuardPasses()) - regenerate langsung tiap payroll yang baris-nya berhasil
        // diimport, supaya nominalnya langsung masuk tanpa perlu klik Regenerate manual.
        $regeneratedCount = 0;
        if ($import->getSuccessUserIds() !== []) {
            $payrollService = app(PayrollService::class);
            $payrolls = Payroll::where('payroll_period_id', $this->importPeriodId)
                ->whereIn('user_id', $import->getSuccessUserIds())
                ->with(['user.profile', 'payrollPeriod'])
                ->get();

            foreach ($payrolls as $payroll) {
                try {
                    $payrollService->regenerateSinglePayroll($payroll, Auth::id());
                    $regeneratedCount++;
                } catch (\Throwable $e) {
                    $this->importErrors[] = "NIP {$payroll->user?->nip}: berhasil diimport tapi gagal regenerate payroll - {$e->getMessage()}";
                }
            }
        }

        if ($this->importErrors === []) {
            Notification::make()
                ->title('Import berhasil')
                ->body("{$this->importSuccessCount} baris berhasil diimport, {$regeneratedCount} payroll otomatis di-regenerate.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title("{$this->importSuccessCount} baris berhasil, " . count($this->importErrors) . ' baris gagal')
                ->body('Lihat detail error di bawah form.')
                ->warning()
                ->send();
        }
    }

    /** Pilih class import sesuai bon_metode company yang lagi dipilih - BonImport (Simple, 1
     * baris/user, kolom nip/nama/nominal) atau BonDetailImport (Detail, banyak baris rincian
     * bernama per user, kolom nip/nama_potongan/nominal). Interface publik kedua class sama
     * persis jadi kode pemanggil (previewImport/confirmImport) tidak perlu tahu bedanya. */
    private function makeImport(bool $preview): BonImport|BonDetailImport
    {
        return $this->bonMetode === 'detail'
            ? new BonDetailImport($this->importPeriodId, $preview)
            : new BonImport($this->importPeriodId, $preview);
    }

    private function importGuardPasses(): bool
    {
        if (! $this->bonAktif) {
            Notification::make()
                ->title('Komponen Bon belum aktif untuk company ini')
                ->body('Aktifkan dulu di halaman Komponen Gaji kalau memang mau pakai Bon.')
                ->danger()
                ->send();

            return false;
        }

        return true;
    }

    private function discardStoredImportFile(): void
    {
        if ($this->importStoredPath !== null) {
            Storage::delete($this->importStoredPath);
            $this->importStoredPath = null;
        }
    }

    public function render()
    {
        return view('livewire.keuangan.payrolls-page');
    }
}
