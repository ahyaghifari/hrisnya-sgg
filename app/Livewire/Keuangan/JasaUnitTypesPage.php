<?php

namespace App\Livewire\Keuangan;

use App\Enum\OrgStructurLevel;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\Payroll\JasaUnitType;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Jasa Unit" — kelola katalog jasa per-divisi (Jasa Perujuk Rawat, Jasa Ambulan, Jasa
 * Paket Infus, dst) plus divisi mana saja yang berhak menerimanya. Nominal per karyawan per
 * periode diisi finance di halaman "Input Jasa Unit" (JasaUnitInputPage) yang terpisah — di sini
 * cuma katalog + assignment divisi, pola add/remove/save-all-rows sama tenureRows/
 * omsetCategoryRows di MappingKomponenGajiPage, tapi berdiri sendiri (bukan tab), sesuai
 * permintaan user.
 */
#[Layout('components.layout', ['title' => 'Jasa Unit'])]
class JasaUnitTypesPage extends Component
{
    public ?string $companyId = null;

    /** @var array<int,array{id:?string,name:string,org_structure_ids:array<int,string>}> */
    public array $rows = [];

    public ?string $viewingAuditKey = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', JasaUnitType::class), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->loadRows();
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
        $this->loadRows();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
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

    public function getCanEditProperty(): bool
    {
        // Bukan can('update', JasaUnitType::class) - halaman ini bulk-edit banyak baris
        // sekaligus (bukan 1 record spesifik), Policy::update() butuh instance model asli,
        // ArgumentCountError kalau dipanggil tanpa instance. Cek nama permission-nya langsung
        // (persis yang dipanggil Policy::update() di baliknya, jadi hasilnya identik).
        return Auth::user()->can('Update:JasaUnitType');
    }

    private function assertCanEdit(): void
    {
        abort_unless(Auth::user()->can('Update:JasaUnitType'), 403);
    }

    private function loadRows(): void
    {
        if (! $this->companyId) {
            $this->rows = [];

            return;
        }

        $this->rows = JasaUnitType::query()
            ->where('company_id', $this->companyId)
            ->with('orgStructures:id')
            ->orderBy('name')
            ->get()
            ->map(fn (JasaUnitType $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'org_structure_ids' => $r->orgStructures->pluck('id')->all(),
            ])
            ->values()
            ->all();
    }

    public function addRow(): void
    {
        $this->assertCanEdit();

        $this->rows[] = ['id' => null, 'name' => '', 'org_structure_ids' => []];
    }

    public function removeRow(int $index): void
    {
        $this->assertCanEdit();

        $row = $this->rows[$index] ?? null;

        if ($row && $row['id']) {
            JasaUnitType::where('id', $row['id'])->delete();
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'rows.*.name' => 'nullable|string|max:150',
        ], [], [
            'rows.*.name' => 'nama jasa',
        ]);

        foreach ($this->rows as $i => $row) {
            if ($row['name'] === '') {
                continue;
            }

            if ($row['id']) {
                $type = JasaUnitType::find($row['id']);
                $type->update(['name' => $row['name']]);
            } else {
                $type = JasaUnitType::create(['company_id' => $this->companyId, 'name' => $row['name']]);
                $this->rows[$i]['id'] = $type->id;
            }

            $type->orgStructures()->sync($row['org_structure_ids']);
        }

        Notification::make()
            ->title('Perubahan berhasil disimpan')
            ->success()
            ->send();
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

    public function render()
    {
        return view('livewire.keuangan.jasa-unit-types-page');
    }
}
