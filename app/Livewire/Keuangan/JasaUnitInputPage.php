<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\OrgStructurLevel;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\JasaUnitDetail;
use App\Models\Payroll\JasaUnitType;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Input nominal Jasa Unit per karyawan per periode — finance pilih DIVISI dulu, baru karyawan
 * (difilter ke divisi itu) dan jasa (difilter ke divisi itu juga), isi nominal, bisa tambah
 * baris berkali-kali lalu simpan sekaligus. `org_structure_id` per baris murni state UI (bukan
 * kolom JasaUnitDetail) - cuma dipakai buat menyempitkan pilihan karyawan/jasa saat input,
 * TIDAK disimpan (kalau butuh tahu divisi karyawan setelah tersimpan, baca dari jabatan
 * aktifnya, bukan dari sini). Pola bulk-row sama persis tenureRows/omsetCategoryRows di
 * MappingKomponenGajiPage, gate simpan KELOLA_INPUT_JASA_UNIT (permission sendiri, sama pola
 * dengan LIHAT_OMSET/KELOLA_OMSET di OmsetPage).
 *
 * Dropdown karyawan SENGAJA native <select> biasa, bukan tom-select (data-tom-select +
 * wire:ignore) seperti pola di halaman lain (mis. KoperasiPage) - di sini daftar karyawannya
 * REAKTIF (options berubah tiap ganti divisi baris itu), sedangkan wire:ignore bikin Livewire
 * TIDAK PERNAH memperbarui DOM di dalamnya sama sekali. Kombinasi keduanya kemarin jadi bug
 * "harus pilih karyawan 2x" (2026-08-05) - pilihan pertama ke-apply ke opsi lama yang sudah
 * basi, baru render berikutnya baru benar. Karena per-divisi employee list-nya sudah pendek
 * (bukan seluruh company), native select tanpa search cukup.
 */
#[Layout('components.layout', ['title' => 'Input Jasa Unit'])]
class JasaUnitInputPage extends Component
{
    public ?string $companyId = null;
    public ?string $periodId = null;

    /** @var array<int, array{id:?string, org_structure_id:?string, user_id:?string, jasa_unit_type_id:?string, amount:?string}> */
    public array $rows = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_INPUT_JASA_UNIT->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? $this->companies->value('id'))
            : Auth::user()->company_id;

        $this->selectDefaultPeriod();
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
        $this->periodId = null;
        $this->rows = [];
        $this->selectDefaultPeriod();
    }

    private function selectDefaultPeriod(): void
    {
        $this->periodId = $this->periods->first()?->id;
        $this->loadRows();
    }

    public function selectPeriod(string $periodId): void
    {
        $this->periodId = $periodId;
        $this->loadRows();
    }

    /** @return \Illuminate\Support\Collection<int,string> company_id yang komponen Jasa Unit-nya aktif */
    private function jasaUnitActiveCompanyIds()
    {
        return CompanyPayrollComponent::query()
            ->where('component', PayrollComponentEnum::JASA_UNIT->value)
            ->where('is_active', true)
            ->pluck('company_id');
    }

    public function getCompaniesProperty()
    {
        return Companies::whereIn('id', $this->jasaUnitActiveCompanyIds())->orderBy('code')->get();
    }

    public function getPeriodsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return PayrollPeriod::query()
            ->where('company_id', $this->companyId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function getDivisionsProperty()
    {
        return $this->companyId
            ? OrgStructure::where('company_id', $this->companyId)
                ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
                ->orderBy('name')
                ->get()
            : collect();
    }

    /**
     * Karyawan aktif company ini, DI DIVISI TERPILIH, yang tipe karyawannya terdaftar sebagai
     * penerima Jasa Unit di halaman "Penerima Komponen Gaji". Divisi belum dipilih -> kosong,
     * memaksa urutan isi: divisi dulu baru karyawan (2026-08-05, sesuai arahan user).
     */
    public function employeesFor(?string $orgStructureId)
    {
        if (! $orgStructureId) {
            return collect();
        }

        $eligibleTypeIds = PayrollComponentRecipient::query()
            ->where('company_id', $this->companyId)
            ->where('component', PayrollComponentEnum::JASA_UNIT->value)
            ->pluck('employee_type_id');

        return User::query()
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->whereHas('profile', fn ($q) => $q->whereIn('employee_type_id', $eligibleTypeIds))
            ->whereHas('activeJobPosition.jobPosition', fn ($q) => $q->where('org_structure_id', $orgStructureId))
            ->orderBy('name')
            ->get();
    }

    /** Jasa yang di-assign ke divisi terpilih (lihat JasaUnitTypesPage) - divisi belum dipilih -> kosong. */
    public function jasaOptionsFor(?string $orgStructureId)
    {
        if (! $orgStructureId) {
            return collect();
        }

        return JasaUnitType::query()
            ->where('company_id', $this->companyId)
            ->whereHas('orgStructures', fn ($q) => $q->where('m_org_structures.id', $orgStructureId))
            ->orderBy('name')
            ->get();
    }

    /** Divisi berubah -> karyawan/jasa yang sebelumnya dipilih di baris itu belum tentu masih
     * valid untuk divisi baru, reset supaya tidak nyangkut nilai lama yang sudah tidak muncul
     * di dropdown (dropdown-nya sendiri otomatis kefilter ulang lewat employeesFor()/jasaOptionsFor()). */
    public function updatedRows($value, string $name): void
    {
        if (! str_ends_with($name, '.org_structure_id')) {
            return;
        }

        [$index] = explode('.', $name, 2);
        $index = (int) $index;

        if (isset($this->rows[$index])) {
            $this->rows[$index]['user_id'] = null;
            $this->rows[$index]['jasa_unit_type_id'] = null;
        }
    }

    private function loadRows(): void
    {
        if (! $this->periodId) {
            $this->rows = [];

            return;
        }

        $this->rows = JasaUnitDetail::query()
            ->where('payroll_period_id', $this->periodId)
            ->with('user.activeJobPosition.jobPosition')
            ->get()
            ->map(fn (JasaUnitDetail $d) => [
                'id' => $d->id,
                // Divisi bukan kolom tersimpan - dibaca ulang dari jabatan aktif karyawan saat
                // ini, cuma buat default tampilan dropdown supaya baris lama tidak muncul kosong.
                'org_structure_id' => $d->user?->activeJobPosition?->jobPosition?->org_structure_id,
                'user_id' => $d->user_id,
                'jasa_unit_type_id' => $d->jasa_unit_type_id,
                'amount' => (string) $d->amount,
            ])
            ->values()
            ->all();
    }

    public function getCanEditProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::KELOLA_INPUT_JASA_UNIT->value);
    }

    public function addRow(): void
    {
        abort_unless($this->canEdit, 403);

        $this->rows[] = ['id' => null, 'org_structure_id' => null, 'user_id' => null, 'jasa_unit_type_id' => null, 'amount' => ''];
    }

    public function removeRow(int $index): void
    {
        abort_unless($this->canEdit, 403);

        $row = $this->rows[$index] ?? null;

        if ($row && $row['id']) {
            JasaUnitDetail::where('id', $row['id'])->delete();
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        abort_unless($this->canEdit, 403);
        abort_unless($this->periodId, 422);

        $this->validate([
            'rows.*.user_id' => 'required|exists:users,id',
            'rows.*.jasa_unit_type_id' => 'required|exists:jasa_unit_types,id',
            'rows.*.amount' => 'required|numeric|min:0.01',
        ], [], [
            'rows.*.user_id' => 'karyawan',
            'rows.*.jasa_unit_type_id' => 'jasa',
            'rows.*.amount' => 'nominal',
        ]);

        foreach ($this->rows as $i => $row) {
            $data = [
                'payroll_period_id' => $this->periodId,
                'user_id' => $row['user_id'],
                'jasa_unit_type_id' => $row['jasa_unit_type_id'],
                'amount' => $row['amount'],
                'created_by' => Auth::id(),
            ];

            if ($row['id']) {
                JasaUnitDetail::where('id', $row['id'])->update($data);
            } else {
                $created = JasaUnitDetail::create($data);
                $this->rows[$i]['id'] = $created->id;
            }
        }

        Notification::make()
            ->title('Perubahan berhasil disimpan')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.jasa-unit-input-page');
    }
}
