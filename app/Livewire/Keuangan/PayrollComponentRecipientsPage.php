<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\PayrollComponentRecipient;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Penerima Komponen Gaji" — 3 kolom: company (kiri) / komponen gaji (tengah) /
 * checklist tipe karyawan penerima (kanan). TIDAK ADA baris utk kombinasi company+component
 * = TIDAK ADA yang eligible (default ketat, disengaja — lihat PayrollComponentRecipient
 * & PayrollService::runComponent()). Simpan = hapus semua baris company ini lalu buat ulang
 * dari checklist yang lagi ditampilkan (bukan upsert per baris).
 */
#[Layout('components.layout', ['title' => 'Penerima Komponen Gaji'])]
class PayrollComponentRecipientsPage extends Component
{
    public ?string $companyId = null;
    public ?string $activeComponent = null;

    /** @var array<string, array<int, string>> component value => list employee_type_id yang eligible */
    public array $selected = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
        $this->loadCompanyState();
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
        $this->activeComponent = null;
        $this->loadCompanyState();
    }

    public function selectComponent(string $component): void
    {
        $this->activeComponent = $component;
    }

    private function loadCompanyState(): void
    {
        if (! $this->companyId) {
            $this->selected = [];

            return;
        }

        $existing = PayrollComponentRecipient::query()
            ->where('company_id', $this->companyId)
            ->get()
            ->groupBy('component');

        $this->selected = collect($this->components)
            ->mapWithKeys(fn (PayrollComponentEnum $c) => [
                $c->value => $existing->get($c->value, collect())->pluck('employee_type_id')->all(),
            ])
            ->all();
    }

    /**
     * Cuma komponen yang AKTIF (toggle ON di halaman Komponen Gaji) buat company terpilih yang
     * ditampilkan — sebelumnya semua PayrollComponentEnum ditampilkan tanpa filter, jadi
     * komponen yang company ini tidak pakai sama sekali tetap muncul di sini dan bisa diisi
     * penerimanya padahal tidak akan pernah dipakai PayrollService::runComponent().
     *
     * @return array<int, PayrollComponentEnum>
     */
    public function getComponentsProperty(): array
    {
        if (! $this->companyId) {
            return [];
        }

        $activeValues = CompanyPayrollComponent::query()
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->pluck('component');

        return collect(PayrollComponentEnum::cases())
            ->reject(fn (PayrollComponentEnum $c) => $c === PayrollComponentEnum::FEE_CLAIM)
            ->filter(fn (PayrollComponentEnum $c) => $activeValues->contains($c->value))
            ->values()
            ->all();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getEmployeeTypesProperty()
    {
        return $this->companyId
            ? EmployeeTypes::where('company_id', $this->companyId)->orderBy('name')->get()
            : collect();
    }

    /**
     * Hapus semua baris recipient company ini lalu buat ulang dari state $selected saat ini
     * (semua komponen sekaligus, bukan cuma yang lagi di-expand) — sesuai permintaan "hapus
     * lalu buat yang baru", bukan diff/upsert per baris.
     */
    public function getCanEditRecipientsProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_PENERIMA_KOMPONEN_GAJI->value);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENERIMA_KOMPONEN_GAJI->value), 403);

        if (! $this->companyId) {
            return;
        }

        DB::transaction(function () {
            PayrollComponentRecipient::where('company_id', $this->companyId)->delete();

            $rows = collect($this->selected)
                ->flatMap(fn (array $employeeTypeIds, string $component) => collect($employeeTypeIds)
                    ->map(fn (string $employeeTypeId) => [
                        'id' => (string) \Illuminate\Support\Str::ulid(),
                        'company_id' => $this->companyId,
                        'component' => $component,
                        'employee_type_id' => $employeeTypeId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]))
                ->values()
                ->all();

            if ($rows !== []) {
                PayrollComponentRecipient::insert($rows);
            }
        });

        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'perusahaan ini';

        Notification::make()
            ->title("Penerima komponen gaji {$companyName} berhasil disimpan")
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.payroll-component-recipients-page');
    }
}
