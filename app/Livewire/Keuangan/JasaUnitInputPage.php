<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Imports\Payroll\JasaUnitDetailImport;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\JasaUnitDetail;
use App\Models\Payroll\JasaUnitType;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Input nominal Jasa Unit per karyawan per periode — finance pilih KARYAWAN langsung (tanpa
 * pilih divisi manual dulu, instruksi user 2026-08-17), lalu jasa difilter OTOMATIS dari divisi
 * jabatan aktif karyawan yang dipilih, isi nominal, bisa tambah baris berkali-kali lalu simpan
 * sekaligus. Pola bulk-row sama persis tenureRows/omsetCategoryRows di MappingKomponenGajiPage,
 * gate simpan KELOLA_INPUT_JASA_UNIT (permission sendiri, sama pola dengan LIHAT_OMSET/
 * KELOLA_OMSET di OmsetPage).
 *
 * Dropdown karyawan pakai tom-select (data-tom-select + wire:ignore, searchable - instruksi
 * user 2026-08-17) - AMAN sekarang karena daftar karyawannya TIDAK REAKTIF lagi (statis per
 * baris, tidak berubah gara-gara field lain di baris yang sama). Beda dari histori bug "harus
 * pilih karyawan 2x" (2026-08-05): itu kejadian waktu daftar karyawan REAKTIF (difilter ulang
 * tiap ganti divisi baris itu) dibungkus wire:ignore, yang bikin Livewire tidak pernah
 * memperbarui opsi di dalamnya. Dropdown JASA di sini yang sekarang reaktif (opsinya berubah
 * tiap ganti karyawan) - itu SENGAJA tetap native <select> biasa (tanpa wire:ignore), supaya
 * tidak mengulang bug yang sama di sisi jasa.
 */
#[Layout('components.layout', ['title' => 'Input Jasa Unit'])]
class JasaUnitInputPage extends Component
{
    use WithFileUploads;

    public ?string $companyId = null;
    public ?string $periodId = null;

    /** @var array<int, array{id:?string, user_id:?string, jasa_unit_type_id:?string, amount:?string}> */
    public array $rows = [];

    public bool $showImportModal = false;

    public $importFile = null;

    public ?string $importStoredPath = null;

    /** @var array<int, array{row:int, nip:string, kode:string, nama:?string, jasa:?string, nominal:float, error:?string}> */
    public array $importPreviewRows = [];

    public ?int $importSuccessCount = null;

    /** @var array<int, string> */
    public array $importErrors = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_INPUT_JASA_UNIT->value), 403);

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

        return PayrollPeriod::acrossCompanies()
            ->where('company_id', $this->companyId)
            ->orderByDesc('start_date')
            ->get();
    }

    /** Karyawan aktif company ini yang tipe karyawannya terdaftar sebagai penerima Jasa Unit di
     * halaman "Penerima Komponen Gaji" — daftar penuh, tidak difilter divisi (2026-08-17, arah
     * baru: pilih karyawan dulu, jasa yang mengikuti divisinya, bukan sebaliknya). */
    public function getEligibleEmployeesProperty()
    {
        if (! $this->companyId) {
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
            ->orderBy('name')
            ->get();
    }

    /** Jasa yang di-assign ke divisi jabatan aktif karyawan terpilih (lihat JasaUnitTypesPage) -
     * karyawan belum dipilih atau belum punya jabatan/divisi -> kosong. */
    public function jasaOptionsFor(?string $userId)
    {
        if (! $userId) {
            return collect();
        }

        $orgStructureId = User::find($userId)?->activeJobPosition?->jobPosition?->org_structure_id;

        if (! $orgStructureId) {
            return collect();
        }

        return JasaUnitType::query()
            ->where('company_id', $this->companyId)
            ->whereHas('orgStructures', fn ($q) => $q->where('m_org_structures.id', $orgStructureId))
            ->orderBy('name')
            ->get();
    }

    /** Karyawan berubah -> jasa yang sebelumnya dipilih di baris itu belum tentu masih valid
     * untuk divisi karyawan baru, reset supaya tidak nyangkut nilai lama yang sudah tidak muncul
     * di dropdown (dropdown-nya sendiri otomatis kefilter ulang lewat jasaOptionsFor()). */
    public function updatedRows($value, string $name): void
    {
        if (! str_ends_with($name, '.user_id')) {
            return;
        }

        [$index] = explode('.', $name, 2);
        $index = (int) $index;

        if (isset($this->rows[$index])) {
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
            ->get()
            ->map(fn (JasaUnitDetail $d) => [
                'id' => $d->id,
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

        $this->rows[] = ['id' => null, 'user_id' => null, 'jasa_unit_type_id' => null, 'amount' => ''];
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
                // Instance update() (fill()->save()), BUKAN Model::where(...)->update() query
                // builder mentah - itu tidak menjalankan cast PayrollEncrypted, amount akan
                // kesimpan PLAINTEXT & crash pas dibaca lagi (DecryptException).
                JasaUnitDetail::find($row['id'])?->update($data);
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

    public function openImportModal(): void
    {
        abort_unless($this->canEdit, 403);
        abort_unless($this->periodId, 422);

        $this->discardImportPreview();
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->discardImportPreview();
        $this->showImportModal = false;
    }

    private function discardImportPreview(): void
    {
        if ($this->importStoredPath !== null) {
            Storage::delete($this->importStoredPath);
        }

        $this->importStoredPath = null;
        $this->importPreviewRows = [];
        $this->importErrors = [];
        $this->importSuccessCount = null;
        $this->reset('importFile');
    }

    /** File baru dipilih di modal -> langsung parse & tampilkan pratinjau tervalidasi, belum
     * ditulis ke database sama sekali (pola sama KaryawanImportPage::updatedFile()). */
    public function updatedImportFile(): void
    {
        abort_unless($this->canEdit, 403);

        $this->importPreviewRows = [];
        $this->importErrors = [];
        $this->importSuccessCount = null;

        if ($this->importStoredPath !== null) {
            Storage::delete($this->importStoredPath);
            $this->importStoredPath = null;
        }

        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv',
        ], [], [
            'importFile' => 'file import',
        ]);

        $this->importStoredPath = $this->importFile->store('temp-jasa-unit-imports');

        $import = new JasaUnitDetailImport($this->companyId, $this->periodId, Auth::id(), preview: true);
        Excel::import($import, Storage::path($this->importStoredPath));

        $this->importPreviewRows = $import->getPreview();
    }

    /** Tombol "Konfirmasi" di modal -> baru di sini datanya ditulis ke database. */
    public function confirmImport(): void
    {
        abort_unless($this->canEdit, 403);
        abort_unless($this->periodId, 422);

        if ($this->importStoredPath === null) {
            return;
        }

        $import = new JasaUnitDetailImport($this->companyId, $this->periodId, Auth::id(), preview: false);

        try {
            Excel::import($import, Storage::path($this->importStoredPath));
        } finally {
            Storage::delete($this->importStoredPath);
            $this->importStoredPath = null;
        }

        $this->importErrors = $import->getErrors();
        $this->importSuccessCount = $import->getSuccessCount();
        $this->importPreviewRows = [];
        $this->reset('importFile');
        $this->loadRows();

        if ($this->importErrors === []) {
            $this->showImportModal = false;

            Notification::make()
                ->title("Import selesai — {$this->importSuccessCount} nominal berhasil disimpan.")
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title("Import selesai — {$this->importSuccessCount} berhasil, " . count($this->importErrors) . ' baris bermasalah.')
            ->body(implode("\n", array_slice($this->importErrors, 0, 10)) . (count($this->importErrors) > 10 ? "\n..." . (count($this->importErrors) - 10) . ' lainnya' : ''))
            ->warning()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.jasa-unit-input-page');
    }
}
