<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Jobs\GeneratePayrollJob;
use App\Jobs\RegeneratePayrollJob;
use App\Models\Master\Companies;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
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
    public ?string $formSignerId = null;

    public bool $showSignerModal = false;
    public ?string $signerEditingId = null;
    public ?string $signerFormPaymentDate = null;
    public ?string $signerFormSignerId = null;

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

    /** Opsi Penandatangan - dipisah dari generated_by (siapa klik Generate), dipilih manual. */
    public function getSignerOptionsProperty()
    {
        return User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
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
        $this->formSignerId = null;
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
        $this->formSignerId = $period->signer_id;
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
            'formSignerId' => 'nullable|exists:users,id',
        ], [], [
            'formCompanyId' => 'perusahaan',
            'formStartDate' => 'dari tanggal',
            'formEndDate' => 'sampai tanggal',
            'formPaymentDate' => 'tanggal bayar',
            'formSignerId' => 'penandatangan',
        ]);

        $data = [
            'company_id' => $this->formCompanyId,
            'start_date' => $this->formStartDate,
            'end_date' => $this->formEndDate,
            'payment_date' => $this->formPaymentDate,
            'signer_id' => $this->formSignerId,
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

    // ── Penandatangan & Tanggal Bayar (bisa diubah kapan saja) ──────────────
    // Sengaja modal TERPISAH dari openEditModal/saveForm di atas - dua field ini murni
    // informasional (tampil di slip / jadwal transfer), tidak dipakai perhitungan payroll sama
    // sekali, jadi tidak perlu ikut terkunci abort_if(processed_at) seperti company/start/end
    // yang memang jadi dasar perhitungan dan tidak boleh berubah pasca-generate.

    public function openSignerModal(string $id): void
    {
        $period = PayrollPeriod::findOrFail($id);

        abort_unless(Auth::user()->can('update', $period), 403);

        $this->signerEditingId = $period->id;
        $this->signerFormPaymentDate = $period->payment_date?->format('Y-m-d');
        $this->signerFormSignerId = $period->signer_id;
        $this->resetErrorBag();
        $this->showSignerModal = true;
    }

    public function closeSignerModal(): void
    {
        $this->showSignerModal = false;
    }

    public function saveSignerModal(): void
    {
        $period = PayrollPeriod::findOrFail($this->signerEditingId);
        abort_unless(Auth::user()->can('update', $period), 403);

        $this->validate([
            'signerFormPaymentDate' => 'nullable|date',
            'signerFormSignerId' => 'nullable|exists:users,id',
        ], [], [
            'signerFormPaymentDate' => 'tanggal bayar',
            'signerFormSignerId' => 'penandatangan',
        ]);

        $period->update([
            'payment_date' => $this->signerFormPaymentDate,
            'signer_id' => $this->signerFormSignerId,
        ]);

        $this->showSignerModal = false;

        Notification::make()
            ->title('Penandatangan & tanggal bayar berhasil diubah')
            ->success()
            ->send();
    }

    // ── Generate / Regenerate ───────────────────────────────────────────────

    public function getCanGeneratePayrollProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::GENERATE_PAYROLL->value);
    }

    public function generatePayroll(string $id): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::GENERATE_PAYROLL->value), 403);

        GeneratePayrollJob::dispatch($id, Auth::id());

        Notification::make()
            ->title('Generate payroll dimulai')
            ->body('Payroll sedang diproses di latar belakang.')
            ->success()
            ->send();
    }

    public function regeneratePayroll(string $id): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::GENERATE_PAYROLL->value), 403);

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
