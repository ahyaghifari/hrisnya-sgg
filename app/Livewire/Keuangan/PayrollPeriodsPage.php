<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Jobs\GeneratePayrollJob;
use App\Jobs\RegeneratePayrollJob;
use App\Models\Master\Companies;
use App\Models\Payroll\PayrollPeriod;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Versi portal dari PayrollPeriodResource (/admin/payroll-periods) — dibangun custom Livewire
 * page, BUKAN lagi reuse Table/Action Filament seperti sebelumnya. Company difilter lewat
 * Livewire (server-side) — SEBELUMNYA List.js dipasang buat sort+pagination di browser, tapi
 * List.js mengunci baris tbody-nya sendiri sehingga tidak ikut ke-morph Livewire tiap company
 * ganti (baris lama nyangkut terus), jadi dicabut.
 */
#[Layout('components.layout', ['title' => 'Periode Gaji'])]
class PayrollPeriodsPage extends Component
{
    public ?string $companyId = null;

    public bool $showFormModal = false;
    public ?string $editingId = null;
    public ?string $formCompanyId = null;
    public ?string $formStartDate = null;
    public ?string $formEndDate = null;
    public ?string $formPaymentDate = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getPeriodsProperty()
    {
        return PayrollPeriod::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->with('company')
            ->orderByDesc('start_date')
            ->get();
    }

    /** Tombol "Tambah Periode Gaji" cuma tampil kalau memang boleh — bukan cuma dicek pas diklik. */
    public function getCanCreatePeriodProperty(): bool
    {
        return Auth::user()->can('create', PayrollPeriod::class);
    }

    // ── Form Tambah/Edit Periode ────────────────────────────────────────────

    public function openCreateModal(): void
    {
        abort_unless(Auth::user()->can('create', PayrollPeriod::class), 403);

        $this->editingId = null;
        $this->formCompanyId = $this->companyId;
        $this->formStartDate = null;
        $this->formEndDate = null;
        $this->formPaymentDate = null;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(string $id): void
    {
        $period = PayrollPeriod::findOrFail($id);

        abort_unless(Auth::user()->can('update', $period), 403);
        abort_if($period->processed_at, 403, 'Periode yang sudah digenerate tidak bisa diedit.');

        $this->editingId = $period->id;
        $this->formCompanyId = $period->company_id;
        $this->formStartDate = $period->start_date->format('Y-m-d');
        $this->formEndDate = $period->end_date->format('Y-m-d');
        $this->formPaymentDate = $period->payment_date?->format('Y-m-d');
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
    }

    public function saveForm(): void
    {
        $ability = $this->editingId ? 'update' : 'create';
        $subject = $this->editingId ? PayrollPeriod::findOrFail($this->editingId) : PayrollPeriod::class;
        abort_unless(Auth::user()->can($ability, $subject), 403);

        $this->validate([
            'formCompanyId' => 'required',
            'formStartDate' => 'required|date',
            'formEndDate' => 'required|date|after_or_equal:formStartDate',
            'formPaymentDate' => 'nullable|date',
        ], [], [
            'formCompanyId' => 'perusahaan',
            'formStartDate' => 'dari tanggal',
            'formEndDate' => 'sampai tanggal',
            'formPaymentDate' => 'tanggal bayar',
        ]);

        $data = [
            'company_id' => $this->formCompanyId,
            'start_date' => $this->formStartDate,
            'end_date' => $this->formEndDate,
            'payment_date' => $this->formPaymentDate,
        ];

        if ($this->editingId) {
            PayrollPeriod::whereKey($this->editingId)->update($data);
        } else {
            PayrollPeriod::create($data);
        }

        $this->showFormModal = false;

        Notification::make()
            ->title($this->editingId ? 'Periode berhasil diubah' : 'Periode baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    // ── Generate / Regenerate ───────────────────────────────────────────────

    public function generatePayroll(string $id): void
    {
        GeneratePayrollJob::dispatch($id, Auth::id());

        Notification::make()
            ->title('Generate payroll dimulai')
            ->body('Payroll sedang diproses di latar belakang.')
            ->success()
            ->send();
    }

    public function regeneratePayroll(string $id): void
    {
        RegeneratePayrollJob::dispatch($id, Auth::id());

        Notification::make()
            ->title('Regenerate payroll dimulai')
            ->body('Payroll yang belum dibayar sedang digenerate ulang di latar belakang.')
            ->warning()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.payroll-periods-page');
    }
}
