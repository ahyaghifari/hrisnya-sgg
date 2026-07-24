<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Filament\Resources\Payroll\ExtraHourAllowances\Schemas\ExtraHourAllowancesForm;
use App\Filament\Resources\Payroll\ExtraHourAllowances\Tables\ExtraHourAllowancesTable;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\PangkatBpjsBasis;
use App\Models\Payroll\PangkatLemburRate;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\PositionAllowance;
use App\Models\Payroll\TenureAllowance;
use App\Models\Payroll\TransportAllowance;
use App\Models\Shift\ExtraHourAllowanceRules;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Satu pintu masuk buat 5 tabel config payroll yang sebelumnya SENGAJA dibiarkan kosong tanpa
 * UI (Fase 0/1/2 — "kerjaan finance lewat UI, bukan migration", lihat
 * planning/2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md) + 2 komponen yang sudah
 * punya Filament Resource sendiri (Kelebihan Jam Kerja, Lembur), digabung di sini juga biar
 * 1 pintu masuk lengkap. Struktur: pilih company -> daftar komponen yang AKTIF (toggle ON) di
 * halaman Komponen Gaji untuk company itu -> form spesifik sesuai aturan Calculator masing-masing.
 *
 * Komponen yang belum ada Calculator-nya sama sekali (tidak masuk requiredSettings/table
 * manapun) tidak muncul di sini — cuma yang punya sumber data pemetaan.
 */
#[Layout('components.layout', ['title' => 'Mapping Komponen Gaji'])]
class MappingKomponenGajiPage extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?string $companyId = null;
    public ?string $activeComponent = null;

    /** @var array<string,string> pangkat_id => base_salary */
    public array $gajiPokokValues = [];

    /** @var array<string,string> pangkat_id => start_kelas_id (kelas terendah pangkat ini) */
    public array $startKelasIdValues = [];

    /** @var array<string,string> pangkat_id => max_kelas_id (kelas tertinggi pangkat ini) */
    public array $maxKelasIdValues = [];

    /** @var array<string,string> pangkat_id => 'ump'|'declared_value'|'' (kosong = hapus mapping) */
    public array $bpjsValues = [];

    /** @var array<string,string> kelas_id => amount */
    public array $transportValues = [];

    /** @var array<string,string> "pangkatId|kelasId" => amount */
    public array $jabatanMatrix = [];

    /** @var array<int,array{id:?string,min_years:string,max_years:string,amount:string}> */
    public array $tenureRows = [];

    /** @var array<string,string> pangkat_id => amount_per_hour (base rate rumus TL/TM) */
    public array $lemburRateValues = [];

    public ?string $viewingAuditKey = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
        $this->activeComponent = null;
    }

    public function selectComponent(string $key): void
    {
        $this->activeComponent = $key;

        match ($key) {
            'gaji_pokok' => $this->loadGajiPokok(),
            'bpjs' => $this->loadBpjs(),
            'tunjangan_transportasi' => $this->loadTransport(),
            'tunjangan_jabatan' => $this->loadJabatan(),
            'tunjangan_masa_kerja' => $this->loadTenure(),
            'lembur' => $this->loadLemburRates(),
            default => null, // kelebihan_jam_kerja dirender lewat $this->table()
        };
    }

    public function getCanEditMappingProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_MAPPING_KOMPONEN_GAJI->value);
    }

    private function assertCanEdit(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_MAPPING_KOMPONEN_GAJI->value), 403);
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

    /** @return array<int, array{key:string,label:string,icon:string}> */
    public function getActiveComponentsProperty(): array
    {
        if (! $this->companyId) {
            return [];
        }

        $active = CompanyPayrollComponent::query()
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->pluck('component');

        $defs = [
            ['key' => 'gaji_pokok', 'label' => 'Gaji Pokok', 'icon' => 'fa-money-bill-wave', 'match' => [PayrollComponentEnum::GAJI_POKOK]],
            ['key' => 'tunjangan_jabatan', 'label' => 'Tunjangan Jabatan', 'icon' => 'fa-briefcase', 'match' => [PayrollComponentEnum::TUNJANGAN_JABATAN]],
            ['key' => 'tunjangan_transportasi', 'label' => 'Tunjangan Transportasi', 'icon' => 'fa-car', 'match' => [PayrollComponentEnum::TUNJANGAN_TRANSPORTASI]],
            ['key' => 'tunjangan_masa_kerja', 'label' => 'Tunjangan Masa Kerja', 'icon' => 'fa-hourglass-half', 'match' => [PayrollComponentEnum::TUNJANGAN_MASA_KERJA]],
            ['key' => 'bpjs', 'label' => 'BPJS (Kesehatan & Ketenagakerjaan)', 'icon' => 'fa-briefcase-medical', 'match' => [PayrollComponentEnum::BPJS_KESEHATAN, PayrollComponentEnum::BPJS_KETENAGAKERJAAN]],
            ['key' => 'kelebihan_jam_kerja', 'label' => 'Kelebihan Jam Kerja', 'icon' => 'fa-business-time', 'match' => [PayrollComponentEnum::KELEBIHAN_JAM_KERJA]],
            ['key' => 'lembur', 'label' => 'Lembur', 'icon' => 'fa-clock', 'match' => [PayrollComponentEnum::LEMBUR]],
        ];

        return collect($defs)
            ->filter(fn (array $d) => collect($d['match'])->contains(fn (PayrollComponentEnum $c) => $active->contains($c->value)))
            ->map(fn (array $d) => ['key' => $d['key'], 'label' => $d['label'], 'icon' => $d['icon']])
            ->values()
            ->all();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getPangkatsProperty()
    {
        return $this->companyId ? Pangkats::where('company_id', $this->companyId)->orderBy('name')->get() : collect();
    }

    public function getKelasListProperty()
    {
        return $this->companyId ? Kelas::where('company_id', $this->companyId)->orderBy('level')->get() : collect();
    }

    // ── Gaji Pokok (pangkat_salaries, 1:1 per pangkat) ──────────────────────

    private function loadGajiPokok(): void
    {
        $this->gajiPokokValues = $this->pangkats
            ->mapWithKeys(fn (Pangkats $p) => [$p->id => (string) ($p->pangkatSalary?->base_salary ?? '')])
            ->all();

        $this->startKelasIdValues = $this->pangkats
            ->mapWithKeys(fn (Pangkats $p) => [$p->id => (string) ($p->start_kelas_id ?? '')])
            ->all();

        $this->maxKelasIdValues = $this->pangkats
            ->mapWithKeys(fn (Pangkats $p) => [$p->id => (string) ($p->max_kelas_id ?? '')])
            ->all();
    }

    public function saveGajiPokok(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'gajiPokokValues.*' => $this->nonNegativeAmountRule(),
            'startKelasIdValues.*' => 'nullable|exists:m_kelas,id',
            'maxKelasIdValues.*' => 'nullable|exists:m_kelas,id',
        ], [], [
            'gajiPokokValues.*' => 'nominal gaji pokok',
            'startKelasIdValues.*' => 'kelas mulai',
            'maxKelasIdValues.*' => 'kelas maksimal',
        ]);

        $kelasLevels = Kelas::whereIn('id', [...array_values($this->startKelasIdValues), ...array_values($this->maxKelasIdValues)])
            ->pluck('level', 'id');

        foreach ($this->pangkats as $p) {
            $pangkatId = $p->id;

            $startId = $this->startKelasIdValues[$pangkatId] ?? '';
            $maxId = $this->maxKelasIdValues[$pangkatId] ?? '';
            $startLevel = $startId !== '' ? $kelasLevels->get($startId) : null;
            $maxLevel = $maxId !== '' ? $kelasLevels->get($maxId) : null;

            if ($startLevel !== null && $maxLevel !== null && $maxLevel < $startLevel) {
                $this->addError("maxKelasIdValues.{$pangkatId}", '"Kelas Maksimal" tidak boleh lebih kecil (levelnya) dari "Kelas Mulai".');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        foreach ($this->gajiPokokValues as $pangkatId => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            PangkatSalary::updateOrCreate(['pangkat_id' => $pangkatId], ['base_salary' => $value]);
        }

        foreach ($this->pangkats as $p) {
            $pangkatId = $p->id;

            $p->update([
                'start_kelas_id' => $this->startKelasIdValues[$pangkatId] !== '' ? $this->startKelasIdValues[$pangkatId] : null,
                'max_kelas_id' => $this->maxKelasIdValues[$pangkatId] !== '' ? $this->maxKelasIdValues[$pangkatId] : null,
            ]);
        }

        $this->notifySaved();
    }

    // ── BPJS (pangkat_bpjs_bases, 1:1 per pangkat — kosong = tidak dipetakan = skip potongan) ──

    private function loadBpjs(): void
    {
        $this->bpjsValues = $this->pangkats
            ->mapWithKeys(fn (Pangkats $p) => [$p->id => (string) ($p->bpjsBasis?->bpjs_basis ?? '')])
            ->all();
    }

    public function saveBpjs(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'bpjsValues.*' => Rule::in(['', 'ump', 'declared_value']),
        ], [], ['bpjsValues.*' => 'basis BPJS']);

        foreach ($this->bpjsValues as $pangkatId => $value) {
            if ($value === '') {
                PangkatBpjsBasis::where('pangkat_id', $pangkatId)->delete();

                continue;
            }

            PangkatBpjsBasis::updateOrCreate(['pangkat_id' => $pangkatId], ['bpjs_basis' => $value]);
        }

        $this->notifySaved();
    }

    // ── Tunjangan Transportasi (transport_allowances, 1:1 per kelas) ───────

    private function loadTransport(): void
    {
        $this->transportValues = $this->kelasList
            ->mapWithKeys(fn (Kelas $k) => [$k->id => (string) ($k->transportAllowance?->amount ?? '')])
            ->all();
    }

    public function saveTransport(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'transportValues.*' => $this->nonNegativeAmountRule(),
        ], [], ['transportValues.*' => 'nominal transportasi']);

        foreach ($this->transportValues as $kelasId => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            TransportAllowance::updateOrCreate(['kelas_id' => $kelasId], ['amount' => $value]);
        }

        $this->notifySaved();
    }

    // ── Lembur (pangkat_lembur_rates, 1:1 per pangkat — pola sama Tunjangan Transportasi).
    // Base rate dipakai LemburCalculator buat rumus TL/TM: base rate / (hari x 7) x multiplier
    // jam. Satu-satunya metode Lembur di sistem ini. ──

    private function loadLemburRates(): void
    {
        $this->lemburRateValues = $this->pangkats
            ->mapWithKeys(fn (Pangkats $p) => [$p->id => (string) ($p->lemburRate?->amount_per_hour ?? '')])
            ->all();
    }

    public function saveLemburRates(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'lemburRateValues.*' => $this->nonNegativeAmountRule(),
        ], [], ['lemburRateValues.*' => 'base rate lembur']);

        foreach ($this->lemburRateValues as $pangkatId => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            PangkatLemburRate::updateOrCreate(['pangkat_id' => $pangkatId], ['amount_per_hour' => $value]);
        }

        $this->notifySaved();
    }

    // ── Tunjangan Jabatan (position_allowances, matrix pangkat x kelas) ────

    private function loadJabatan(): void
    {
        $pangkats = $this->pangkats;
        $kelasList = $this->kelasList;

        $existing = PositionAllowance::query()
            ->whereIn('pangkat_id', $pangkats->pluck('id'))
            ->get()
            ->keyBy(fn (PositionAllowance $r) => $r->pangkat_id . '|' . $r->kelas_id);

        $this->jabatanMatrix = [];

        foreach ($pangkats as $p) {
            foreach ($kelasList as $k) {
                $key = $p->id . '|' . $k->id;
                $this->jabatanMatrix[$key] = (string) ($existing[$key]->amount ?? '');
            }
        }
    }

    public function saveJabatan(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'jabatanMatrix.*' => $this->nonNegativeAmountRule(),
        ], [], ['jabatanMatrix.*' => 'nominal tunjangan jabatan']);

        foreach ($this->jabatanMatrix as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            [$pangkatId, $kelasId] = explode('|', $key, 2);

            PositionAllowance::updateOrCreate(
                ['pangkat_id' => $pangkatId, 'kelas_id' => $kelasId],
                ['amount' => $value]
            );
        }

        $this->notifySaved();
    }

    // ── Tunjangan Masa Kerja (tenure_allowances, bracket tahun kerja per company) ──

    private function loadTenure(): void
    {
        $this->tenureRows = TenureAllowance::query()
            ->where('company_id', $this->companyId)
            ->orderBy('min_years')
            ->get()
            ->map(fn (TenureAllowance $r) => [
                'id' => $r->id,
                'min_years' => (string) $r->min_years,
                'max_years' => $r->max_years !== null ? (string) $r->max_years : '',
                'amount' => (string) $r->amount,
            ])
            ->values()
            ->all();
    }

    public function addTenureRow(): void
    {
        $this->assertCanEdit();

        $this->tenureRows[] = ['id' => null, 'min_years' => '', 'max_years' => '', 'amount' => ''];
    }

    public function removeTenureRow(int $index): void
    {
        $this->assertCanEdit();

        $row = $this->tenureRows[$index] ?? null;

        if ($row && $row['id']) {
            TenureAllowance::where('id', $row['id'])->delete();
        }

        unset($this->tenureRows[$index]);
        $this->tenureRows = array_values($this->tenureRows);
    }

    public function saveTenure(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'tenureRows.*.min_years' => $this->nonNegativeIntegerRule(),
            'tenureRows.*.max_years' => $this->nonNegativeIntegerRule(),
            'tenureRows.*.amount' => $this->nonNegativeAmountRule(),
        ], [], [
            'tenureRows.*.min_years' => 'dari tahun',
            'tenureRows.*.max_years' => 'sampai tahun',
            'tenureRows.*.amount' => 'nominal',
        ]);

        // Baris yang beneran mau disimpan (min_years + amount terisi, sama syarat yang dipakai
        // saat simpan) — cek urutan & tumpang tindih cuma di antara baris ini, key ASLI
        // (bukan re-index) supaya addError() nempel ke input yang benar di blade.
        $rowsToSave = collect($this->tenureRows)->filter(fn (array $r) => $r['min_years'] !== '' && $r['amount'] !== '');

        foreach ($rowsToSave as $i => $row) {
            $min = (int) $row['min_years'];
            $max = $row['max_years'] !== '' ? (int) $row['max_years'] : null;

            if ($max !== null && $max < $min) {
                $this->addError("tenureRows.{$i}.max_years", '"Sampai Tahun" tidak boleh lebih kecil dari "Dari Tahun".');
            }
        }

        foreach ($rowsToSave as $i => $a) {
            foreach ($rowsToSave as $j => $b) {
                if ($i >= $j) {
                    continue;
                }

                $aMin = (int) $a['min_years'];
                $aMax = $a['max_years'] !== '' ? (int) $a['max_years'] : PHP_INT_MAX;
                $bMin = (int) $b['min_years'];
                $bMax = $b['max_years'] !== '' ? (int) $b['max_years'] : PHP_INT_MAX;

                // Strict "<" di kedua sisi — jenjang yang cuma BERSENTUHAN pas di titik yang
                // sama (0-2 lanjut 2-5) TIDAK dianggap tumpang tindih, karena batas atas
                // bersifat eksklusif secara konsep (tahun ke-2 masuk jenjang berikutnya, bukan
                // dua-duanya). Overlap sungguhan (mis. 0-3 vs 2-5) tetap kena.
                if ($aMin < $bMax && $bMin < $aMax) {
                    $this->addError("tenureRows.{$j}.min_years", 'Jenjang tahun tumpang tindih dengan baris lain.');
                }
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        foreach ($this->tenureRows as $i => $row) {
            if ($row['min_years'] === '' || $row['amount'] === '') {
                continue;
            }

            $data = [
                'company_id' => $this->companyId,
                'min_years' => $row['min_years'],
                'max_years' => $row['max_years'] !== '' ? $row['max_years'] : null,
                'amount' => $row['amount'],
            ];

            if ($row['id']) {
                TenureAllowance::where('id', $row['id'])->update($data);
            } else {
                $created = TenureAllowance::create($data);
                $this->tenureRows[$i]['id'] = $created->id;
            }
        }

        $this->notifySaved();
    }

    // ── Kelebihan Jam Kerja — reuse Filament Form/Table yang sudah ada
    // (App\Filament\Resources\Payroll\ExtraHourAllowances), sama pola dengan IncentivesPage.php.
    // modifyQueryUsing dipakai supaya list SELALU mengikuti company yang dipilih di halaman ini,
    // bukan filter company terpisah di dalam tabelnya sendiri.

    public function table(Table $table): Table
    {
        return match ($this->activeComponent) {
            'kelebihan_jam_kerja' => ExtraHourAllowancesTable::configure($table)
                ->query(ExtraHourAllowanceRules::query())
                ->modifyQueryUsing(fn ($query) => $query->where('company_id', $this->companyId))
                ->headerActions([
                    CreateAction::make()
                        ->modalWidth('lg')
                        ->mutateFormDataUsing(fn (array $data) => [...$data, 'company_id' => $this->companyId]),
                ]),
            default => $table->query(CompanyPayrollComponent::query()->whereRaw('1 = 0')),
        };
    }

    public function getDefaultActionSchemaResolver(Action $action): ?Closure
    {
        if (! ($action instanceof CreateAction || $action instanceof EditAction)) {
            return null;
        }

        return match ($this->activeComponent) {
            'kelebihan_jam_kerja' => fn (Schema $schema): Schema => ExtraHourAllowancesForm::configure($schema),
            default => null,
        };
    }

    /**
     * Rule closure alih-alih 'nullable|numeric|min:0' — 'nullable' Laravel cuma menganggap
     * NULL sebagai kosong, sedangkan input di halaman ini pakai string kosong '' buat "belum
     * diisi, skip saat simpan" (lihat komentar di tiap save*()). Closure ini yang benar-benar
     * meloloskan '' tanpa ikut divalidasi sebagai angka.
     */
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

    private function nonNegativeIntegerRule(): array
    {
        return [function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === '' || $value === null) {
                return;
            }

            if (! is_numeric($value) || (int) $value != $value || (int) $value < 0) {
                $fail('Nilai harus berupa angka bulat 0 atau lebih besar.');
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
        return view('livewire.keuangan.mapping-komponen-gaji-page');
    }
}
