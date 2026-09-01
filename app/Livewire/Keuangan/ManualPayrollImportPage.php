<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\Concerns\HasSlipSendStatus;
use App\Jobs\SendPayrollSlipJob;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Notifications\PayrollPaid;
use App\Services\Payroll\ManualPayrollExcelImporter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Import payroll final dari Excel skema RSU ke tabel Payroll/PayrollItem existing.
 * Pola upload -> pratinjau (belum tulis DB) -> Konfirmasi Import, sama seperti
 * KaryawanImportPage. Lihat knowledge/markdown/import-payroll-excel-manual.md.
 */
#[Layout('components.layout', ['title' => 'Import Payroll Manual'])]
class ManualPayrollImportPage extends Component
{
    use HasSlipSendStatus;
    use WithFileUploads;

    public ?string $companyId = null;
    public ?string $periodId = null;
    public string $search = '';

    /** id payroll yang dicentang (buat aksi massal Tandai Dibayar / Kirim Slip). */
    public array $selected = [];

    public $file = null;
    public ?string $storedPath = null;

    /** Hasil ManualPayrollExcelImporter::preview() */
    public array $preview = [];
    public bool $imported = false;
    public array $result = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::GENERATE_PAYROLL->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
        $this->periodId = null;
        $this->selected = [];
        $this->discardPreview();
    }

    public function updatedPeriodId(): void
    {
        $this->selected = [];
        $this->discardPreview();
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

        return PayrollPeriod::query()
            ->where('company_id', $this->companyId)
            ->withCount('payrolls')
            ->orderByDesc('start_date')
            ->get();
    }

    public function getSelectedPeriodProperty(): ?PayrollPeriod
    {
        return $this->periodId
            ? PayrollPeriod::where('company_id', $this->companyId)->find($this->periodId)
            : null;
    }

    // ── Tabel payroll periode terpilih (sama pola PayrollsPage, decrypt aman per baris) ──

    /** Payroll::query() sudah di-scope ke periode terpilih — dipakai HasSlipSendStatus. */
    protected function baseSlipQuery(): \Illuminate\Contracts\Database\Eloquent\Builder
    {
        return Payroll::query()->where('payroll_period_id', $this->periodId ?? '-');
    }

    public function getPayrollsProperty()
    {
        if (! $this->periodId) {
            return collect();
        }

        $search = trim($this->search);

        $payrolls = $this->applySlipStatusFilter($this->baseSlipQuery())
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('nip', 'like', "%{$search}%")))
            ->with(['user', 'payrollPeriod'])
            ->orderByDesc('created_at')
            ->get();

        foreach ($payrolls as $payroll) {
            $payroll->decryptFailed = false;
            foreach (['gross_salary' => 'safeGross', 'total_deductions' => 'safeDeductions', 'net_salary' => 'safeNet'] as $field => $safe) {
                try {
                    $payroll->{$safe} = $payroll->{$field};
                } catch (\Throwable) {
                    $payroll->{$safe} = null;
                    $payroll->decryptFailed = true;
                }
            }
        }

        return $payrolls;
    }

    public function getCanUpdatePayrollProperty(): bool
    {
        return Auth::user()->can('Update:Payroll');
    }

    public function getSelectedAllPaidProperty(): bool
    {
        return $this->selected !== []
            && ! Payroll::whereIn('id', $this->selected)->where('is_paid', false)->exists();
    }

    public function toggleSelectAll(bool $checked): void
    {
        $this->selected = $checked ? $this->payrolls->pluck('id')->all() : [];
    }

    public function markSelectedAsPaid(): void
    {
        abort_unless($this->canUpdatePayroll, 403);

        $newlyPaid = Payroll::whereIn('id', $this->selected)->where('is_paid', false)
            ->with(['user', 'payrollPeriod'])->get();

        Payroll::whereIn('id', $newlyPaid->pluck('id'))->update(['is_paid' => true]);
        foreach ($newlyPaid as $payroll) {
            $payroll->user?->notify(new PayrollPaid($payroll));
        }

        $this->selected = [];
        Notification::make()->title("{$newlyPaid->count()} payroll ditandai sudah dibayar")->success()->send();
    }

    public function sendSlipsToSelected(): void
    {
        abort_unless($this->canUpdatePayroll, 403);
        abort_unless($this->selectedAllPaid, 403, 'Semua payroll yang dipilih harus sudah dibayar dulu.');

        $payrolls = Payroll::whereIn('id', $this->selected)->with('user.profile')->get();
        $skipped = 0;

        foreach ($payrolls as $payroll) {
            if (! ($payroll->user?->profile?->email_pribadi ?: $payroll->user?->email)) {
                $skipped++;

                continue;
            }
            SendPayrollSlipJob::dispatch($payroll->id);
        }

        $this->selected = [];
        Notification::make()
            ->title(($payrolls->count() - $skipped) . ' slip gaji sedang dikirim')
            ->body($skipped > 0 ? "{$skipped} dilewati karena karyawan belum punya email." : 'Diproses via queue.')
            ->success()
            ->send();
    }

    /** Buang semua payroll (+ item + manual line potongan khusus) periode ini yang BELUM dibayar. */
    public function clearUnpaid(): void
    {
        abort_unless($this->canUpdatePayroll, 403);
        abort_unless($this->periodId, 400);

        $ids = Payroll::where('payroll_period_id', $this->periodId)->where('is_paid', false)->pluck('id');
        PayrollItem::whereIn('payroll_id', $ids)->delete();
        Payroll::whereIn('id', $ids)->delete();
        PayrollManualLine::where('payroll_period_id', $this->periodId)
            ->where('component', ManualPayrollExcelImporter::SOURCE_POTONGAN_KHUSUS)
            ->delete();

        $this->selected = [];
        Notification::make()->title("{$ids->count()} payroll belum-dibayar dihapus")->success()->send();
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

    public function updatedFile(): void
    {
        $this->imported = false;
        $this->result = [];
        $this->runPreview();
    }

    private function runPreview(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::GENERATE_PAYROLL->value), 403);

        $this->preview = [];
        $this->discardStoredFile();

        $this->validate([
            'companyId' => 'required',
            'periodId' => 'required',
            'file' => 'required|file|mimes:xlsx,xls',
        ], [], [
            'companyId' => 'perusahaan',
            'periodId' => 'periode gaji',
            'file' => 'file Excel',
        ]);

        $this->storedPath = $this->file->store('temp-payroll-imports');

        try {
            $this->preview = app(ManualPayrollExcelImporter::class)
                ->preview(Storage::path($this->storedPath), $this->companyId);
        } catch (\Throwable $e) {
            $this->discardStoredFile();
            Notification::make()->title('Gagal membaca file')->body($e->getMessage())->danger()->send();
        }
    }

    public function confirmImport(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::GENERATE_PAYROLL->value), 403);

        if (! $this->storedPath || ! $this->periodId) {
            return;
        }

        $period = PayrollPeriod::where('company_id', $this->companyId)->findOrFail($this->periodId);

        try {
            $this->result = app(ManualPayrollExcelImporter::class)
                ->import(Storage::path($this->storedPath), $period, Auth::user());
            $this->imported = true;
            $this->preview = [];

            Notification::make()
                ->title('Import selesai')
                ->body("{$this->result['dibuat']} payroll dibuat, {$this->result['dilewati']} baris dilewati.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Import gagal')->body($e->getMessage())->danger()->send();
        } finally {
            $this->discardStoredFile();
            $this->reset('file');
        }
    }

    public function cancelPreview(): void
    {
        $this->discardPreview();
        $this->reset('file');
    }

    private function discardPreview(): void
    {
        $this->preview = [];
        $this->imported = false;
        $this->result = [];
        $this->discardStoredFile();
    }

    private function discardStoredFile(): void
    {
        if ($this->storedPath) {
            Storage::delete($this->storedPath);
            $this->storedPath = null;
        }
    }

    public function render()
    {
        return view('livewire.keuangan.manual-payroll-import-page');
    }
}
