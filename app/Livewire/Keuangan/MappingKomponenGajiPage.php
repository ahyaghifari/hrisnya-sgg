<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\OmsetCategory;
use App\Models\Payroll\PangkatBpjsBasis;
use App\Models\Payroll\PangkatLemburRate;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\LegacyPositionAllowance;
use App\Models\Payroll\PositionAllowance;
use App\Models\Payroll\TenureAllowance;
use App\Models\Payroll\TransportAllowance;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Satu pintu masuk buat 5 tabel config payroll yang sebelumnya SENGAJA dibiarkan kosong tanpa
 * UI (Fase 0/1/2 — "kerjaan finance lewat UI, bukan migration", lihat
 * planning/2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md) + Lembur yang sudah
 * punya Filament Resource sendiri, digabung di sini juga biar
 * 1 pintu masuk lengkap. Struktur: pilih company -> daftar komponen yang AKTIF (toggle ON) di
 * halaman Komponen Gaji untuk company itu -> form spesifik sesuai aturan Calculator masing-masing.
 *
 * Komponen yang belum ada Calculator-nya sama sekali (tidak masuk requiredSettings/table
 * manapun) tidak muncul di sini — cuma yang punya sumber data pemetaan.
 */
#[Layout('components.layout', ['title' => 'Mapping Komponen Gaji'])]
class MappingKomponenGajiPage extends Component
{
    public ?string $companyId = null;
    public ?string $activeComponent = null;

    /** @var array<string,string> pangkat_id => base_salary */
    public array $gajiPokokValues = [];

    /** @var array<string,string> pangkat_id => 'ump'|'declared_value'|'' (kosong = hapus mapping) */
    public array $bpjsValues = [];

    /** @var array<string,string> kelas_id => amount */
    public array $transportValues = [];

    /** @var array<string,string> "pangkatId|kelasId" => amount */
    public array $jabatanMatrix = [];

    /** @var array<string,string> "pangkatId|kelasId" => amount, dipakai IncentiveCalculator metode "auto" (lihat §Tunjangan Jabatan Lama) */
    public array $jabatanLegacyMatrix = [];

    /** @var array<int,array{id:?string,min_years:string,max_years:string,amount:string}> */
    public array $tenureRows = [];

    /** @var array<int,array{id:?string,name:string}> */
    public array $omsetCategoryRows = [];

    /** @var array<string,string> pangkat_id => amount_per_hour (base rate rumus TL/TM) */
    public array $lemburRateValues = [];

    public ?string $viewingAuditKey = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_MAPPING_KOMPONEN_GAJI->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
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
            'jasa_pelayanan' => $this->loadOmsetCategories(),
            default => null,
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
            ['key' => 'lembur', 'label' => 'Lembur', 'icon' => 'fa-clock', 'match' => [PayrollComponentEnum::LEMBUR]],
            ['key' => 'jasa_pelayanan', 'label' => 'Jasa Pelayanan', 'icon' => 'fa-hand-holding-medical', 'match' => [PayrollComponentEnum::JASA_PELAYANAN]],
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
        return $this->companyId
            ? Pangkats::where('company_id', $this->companyId)->with(['startKelas', 'maxKelas'])->orderByDesc('level')->get()
            : collect();
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
    }

    public function saveGajiPokok(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'gajiPokokValues.*' => $this->nonNegativeAmountRule(),
        ], [], [
            'gajiPokokValues.*' => 'nominal gaji pokok',
        ]);

        foreach ($this->gajiPokokValues as $pangkatId => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            PangkatSalary::updateOrCreate(['pangkat_id' => $pangkatId], ['base_salary' => $value]);
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

        $existingLegacy = LegacyPositionAllowance::query()
            ->whereIn('pangkat_id', $pangkats->pluck('id'))
            ->get()
            ->keyBy(fn (LegacyPositionAllowance $r) => $r->pangkat_id . '|' . $r->kelas_id);

        $this->jabatanMatrix = [];
        $this->jabatanLegacyMatrix = [];

        foreach ($pangkats as $p) {
            foreach ($kelasList as $k) {
                $key = $p->id . '|' . $k->id;
                $this->jabatanMatrix[$key] = (string) ($existing[$key]->amount ?? '');
                $this->jabatanLegacyMatrix[$key] = (string) ($existingLegacy[$key]->amount ?? '');
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

    /**
     * Tunjangan Jabatan LAMA (legacy_position_allowances) — cuma dipakai IncentiveCalculator
     * metode "auto": selisih (lama - baru) jadi Insentif penutup transisi, dihitung live per
     * pangkat_id/kelas_id karyawan saat Generate. TIDAK dikunci rentang Kelas Dasar/Maksimal
     * Pangkat seperti matrix baru — data lama independen dari rentang yang berlaku sekarang.
     */
    public function saveJabatanLegacy(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'jabatanLegacyMatrix.*' => $this->nonNegativeAmountRule(),
        ], [], ['jabatanLegacyMatrix.*' => 'nominal tunjangan jabatan lama']);

        foreach ($this->jabatanLegacyMatrix as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            [$pangkatId, $kelasId] = explode('|', $key, 2);

            LegacyPositionAllowance::updateOrCreate(
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

    // ── Jasa Pelayanan: Kategori Omset (omset_categories, daftar nama per company) — cuma
    // kelola KATEGORI di sini; nominal per kategori per periode diisi di halaman input
    // Omset Rumah Sakit yang terpisah (belum dibangun), sama seperti daftar kategori di sini
    // cuma jadi baris tetap yang dipakai halaman itu. ──

    private function loadOmsetCategories(): void
    {
        $this->omsetCategoryRows = OmsetCategory::query()
            ->where('company_id', $this->companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (OmsetCategory $r) => ['id' => $r->id, 'name' => $r->name])
            ->values()
            ->all();
    }

    public function addOmsetCategoryRow(): void
    {
        $this->assertCanEdit();

        $this->omsetCategoryRows[] = ['id' => null, 'name' => ''];
    }

    public function removeOmsetCategoryRow(int $index): void
    {
        $this->assertCanEdit();

        $row = $this->omsetCategoryRows[$index] ?? null;

        if ($row && $row['id']) {
            OmsetCategory::where('id', $row['id'])->delete();
        }

        unset($this->omsetCategoryRows[$index]);
        $this->omsetCategoryRows = array_values($this->omsetCategoryRows);
    }

    public function saveOmsetCategories(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'omsetCategoryRows.*.name' => 'nullable|string|max:100',
        ], [], [
            'omsetCategoryRows.*.name' => 'nama kategori',
        ]);

        foreach ($this->omsetCategoryRows as $i => $row) {
            if ($row['name'] === '') {
                continue;
            }

            if ($row['id']) {
                OmsetCategory::where('id', $row['id'])->update(['name' => $row['name']]);
            } else {
                $created = OmsetCategory::create(['company_id' => $this->companyId, 'name' => $row['name']]);
                $this->omsetCategoryRows[$i]['id'] = $created->id;
            }
        }

        $this->notifySaved();
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
