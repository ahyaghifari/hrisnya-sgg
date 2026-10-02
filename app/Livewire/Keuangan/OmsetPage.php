<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\JobPositionReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\OmsetCategory;
use App\Models\Payroll\OmsetDetail;
use App\Models\Payroll\PayrollPeriod;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Rincian Omset per kategori per periode gaji (dijumlah jadi Total Omset dipakai
 * JasaPelayananCalculator). JE per Jabatan cuma ditampilkan READ-ONLY di sini sebagai
 * konteks — JE itu atribut Jabatan, edit satu-satunya ada di Kepegawaian
 * (App\Livewire\Kepegawaian\JobEvaluationPage), bukan di sini, supaya tidak ada 2 tempat
 * edit untuk 1 kolom yang sama (job_positions.job_evaluation_score).
 */
#[Layout('components.layout', ['title' => 'Omset'])]
class OmsetPage extends Component
{
    public ?string $companyId = null;
    public ?string $periodId = null;

    /** @var array<string,string> omset_category_id => amount */
    public array $omsetValues = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_OMSET->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? $this->companies->value('id'))
            : Auth::user()->company_id;

        $this->selectDefaultPeriod();
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
        $this->periodId = null;
        $this->omsetValues = [];
        $this->selectDefaultPeriod();
    }

    private function selectDefaultPeriod(): void
    {
        $this->periodId = $this->periods->first()?->id;
        $this->loadOmset();
    }

    public function selectPeriod(string $periodId): void
    {
        $this->periodId = $periodId;
        $this->loadOmset();
    }

    /** @return \Illuminate\Support\Collection<int,string> company_id yang komponen Jasa Pelayanan-nya aktif */
    private function jaspelActiveCompanyIds()
    {
        return CompanyPayrollComponent::query()
            ->where('component', PayrollComponentEnum::JASA_PELAYANAN->value)
            ->where('is_active', true)
            ->pluck('company_id');
    }

    public function getCompaniesProperty()
    {
        return Companies::whereIn('id', $this->jaspelActiveCompanyIds())->orderBy('code')->get();
    }

    public function getPeriodsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return PayrollPeriod::acrossCompanies()
            ->where('company_id', $this->companyId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function getCategoriesProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return OmsetCategory::where('company_id', $this->companyId)->orderBy('name')->get();
    }

    /** JE per jabatan — READ-ONLY, cuma konteks. Edit di Kepegawaian (JobEvaluationPage). */
    public function getJobPositionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return JobPositions::query()
            ->where('company_id', $this->companyId)
            ->where('status', JobPositionReqStatus::APPROVED->value)
            ->orderBy('name')
            ->get();
    }

    /** Total Omset LIVE dari input yang sedang diedit (belum tentu sudah disimpan). */
    public function getTotalOmsetProperty(): float
    {
        return collect($this->omsetValues)->sum(fn ($v) => is_numeric($v) ? (float) $v : 0);
    }

    public function getTotalJeProperty(): int
    {
        return (int) $this->jobPositions->sum('job_evaluation_score');
    }

    private function loadOmset(): void
    {
        $categories = $this->categories;

        $existing = $this->periodId
            ? OmsetDetail::where('payroll_period_id', $this->periodId)->pluck('amount', 'omset_category_id')
            : collect();

        $this->omsetValues = $categories
            ->mapWithKeys(fn (OmsetCategory $c) => [$c->id => (string) ($existing[$c->id] ?? '')])
            ->all();
    }

    public function getCanEditProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::KELOLA_OMSET->value);
    }

    public function saveOmset(): void
    {
        abort_unless($this->canEdit, 403);
        abort_unless($this->periodId, 422);

        $this->validate([
            'omsetValues.*' => $this->nonNegativeAmountRule(),
        ], [], ['omsetValues.*' => 'nominal omset']);

        foreach ($this->omsetValues as $categoryId => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            OmsetDetail::updateOrCreate(
                ['payroll_period_id' => $this->periodId, 'omset_category_id' => $categoryId],
                ['amount' => $value]
            );
        }

        $this->notifySaved();
    }

    private function nonNegativeAmountRule(): array
    {
        return [function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === '' || $value === null) {
                return;
            }

            if (! is_numeric($value) || (float) $value < 0) {
                $fail('Nilai harus berupa angka 0 atau lebih besar.');
            }
        }];
    }

    private function notifySaved(): void
    {
        Notification::make()
            ->title('Perubahan berhasil disimpan')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.omset-page');
    }
}
