<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Enum\MeritPoint\MeritPointEvent;
use App\Enum\MeritPoint\MeritPointTriggerType;
use App\Enum\MeritPoint\MeritPointType;
use App\Models\Master\Companies;
use App\Models\MeritPoint\MeritPointRule;
use App\Models\MeritPoint\MeritPointTransaction;
use App\Models\Setting;
use App\Models\UserHrBalance;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Satu halaman Merit Poin: rangkuman skor karyawan + riwayat transaksi terbaru, dan master
 * aturan (otomatis & manual) di halaman yang sama. Menggantikan dua resource Filament terpisah
 * (Merit Poin / Aturan Merit Poin) - resource Filament-nya dibiarkan, tapi tidak lagi dilink di menu portal.
 */
#[Layout('components.layout', ['title' => 'Merit Poin'])]
class MeritPointPage extends Component
{
    public ?string $companyId = null;

    public bool $showForm = false;
    public ?string $ruleId = null;
    public string $name = '';
    // Sengaja tanpa default (instruksi user 2026-09-27) - admin harus pilih sendiri Reward/Penalty,
    // tidak boleh kepencet Simpan tanpa sadar dengan tipe yang kepilih diam-diam.
    public string $type = '';
    public int|string $points = 0;
    // Kosong = null (tidak ada poin bonus, rule tetap pakai `points` walau saldo sudah >= 100).
    // Cuma relevan buat Tipe Reward - lihat MeritPointService::effectivePoints().
    public int|string|null $bonus_points = '';
    public bool $is_active = true;

    /** @var array<string, array{id: ?string, type: string, points: int|string, bonus_points: int|string, is_active: bool}>
     * keyBy MeritPointEvent->value - selalu berisi SEMUA kejadian (App\Enum\MeritPoint::cases()),
     * baris yang belum pernah disimpan tampil dengan default kosong/nonaktif (pola sama
     * ExtraShiftRule - "baris tidak ada = tidak berlaku"), bukan cuma yang sudah pernah dibuat
     * admin. Ini yang membedakan section "Otomatis" dari "Aturan Poin" (manual): otomatis TIDAK
     * ditambah/dihapus admin, cuma 4 baris tetap (1 per MeritPointEvent) yang diisi. */
    public array $automaticForm = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:MeritPointRule') || Auth::user()->can(\App\Enum\CustomPermissionEnum::BERI_MERIT_POINT->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
        $this->loadAutomaticForm();
    }

    public function updatedCompanyId(): void
    {
        // Property Livewire bisa di-$set langsung dari request - user non-global tidak boleh pindah company.
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->loadAutomaticForm();
    }

    private function loadAutomaticForm(): void
    {
        $existing = MeritPointRule::where('company_id', $this->companyId)
            ->where('trigger_type', MeritPointTriggerType::AUTOMATIC->value)
            ->get()
            ->keyBy(fn (MeritPointRule $r) => $r->event->value);

        $this->automaticForm = collect(MeritPointEvent::cases())
            ->mapWithKeys(function (MeritPointEvent $event) use ($existing) {
                $rule = $existing->get($event->value);

                return [$event->value => [
                    'id' => $rule?->id,
                    'type' => $rule?->type->value ?? MeritPointType::PENALTY->value,
                    'points' => $rule?->points ?? 0,
                    'bonus_points' => $rule?->bonus_points ?? '',
                    'is_active' => $rule?->is_active ?? false,
                ]];
            })
            ->all();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getEnabledProperty(): bool
    {
        return (bool) Setting::resolve(
            KepegawaianSettingEnum::MERIT_POINT_ENABLED->value,
            $this->companyId,
            filter_var(KepegawaianSettingEnum::MERIT_POINT_ENABLED->default(), FILTER_VALIDATE_BOOLEAN)
        );
    }

    public function getCanManageProperty(): bool
    {
        return Auth::user()->can('Create:MeritPointRule') || Auth::user()->can('Update:MeritPointRule');
    }

    /** Karyawan dengan skor terendah di company terpilih (baris ada hanya kalau pernah kena transaksi). */
    public function getLowestBalancesProperty()
    {
        return UserHrBalance::with('user')
            ->whereHas('user', fn ($q) => $q->whereCompany($this->companyId))
            ->orderBy('merit_point')
            ->limit(10)
            ->get();
    }

    public function getSummaryProperty(): array
    {
        $tx = MeritPointTransaction::whereHas('user', fn ($q) => $q->whereCompany($this->companyId))
            ->where('created_at', '>=', now()->startOfMonth());

        return [
            'karyawan' => UserHrBalance::whereHas('user', fn ($q) => $q->whereCompany($this->companyId))->where('merit_point', '<', 100)->count(),
            'penalti' => (int) (clone $tx)->where('points', '<', 0)->sum('points'),
            'reward' => (int) (clone $tx)->where('points', '>', 0)->sum('points'),
        ];
    }

    public function getRecentTransactionsProperty()
    {
        return MeritPointTransaction::with(['user', 'rule'])
            ->whereHas('user', fn ($q) => $q->whereCompany($this->companyId))
            ->latest('created_at')
            ->limit(15)
            ->get();
    }

    /** Cuma rule MANUAL - Otomatis (trigger_type=automatic) punya section tersendiri
     * (automaticForm, 4 baris tetap), tidak ikut di daftar "Aturan Poin" ini lagi. */
    public function getRulesProperty()
    {
        return MeritPointRule::where('company_id', $this->companyId)
            ->where('trigger_type', MeritPointTriggerType::MANUAL->value)
            ->orderByDesc('is_active')->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        abort_unless(Auth::user()->can('Create:MeritPointRule'), 403);
        $this->resetForm();
        $this->showForm = true;
    }

    /** @param string $id MeritPointRule::id - cuma rule manual yang boleh diedit lewat modal ini
     * (findOrFail dengan filter trigger_type manual, bukan cuma company_id). */
    public function edit(string $id): void
    {
        abort_unless(Auth::user()->can('Update:MeritPointRule'), 403);
        $rule = MeritPointRule::where('company_id', $this->companyId)
            ->where('trigger_type', MeritPointTriggerType::MANUAL->value)
            ->findOrFail($id);

        $this->ruleId = $rule->id;
        $this->name = $rule->name;
        $this->type = $rule->type->value;
        $this->points = $rule->points;
        $this->bonus_points = $rule->bonus_points ?? '';
        $this->is_active = $rule->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function toggleActive(string $id): void
    {
        abort_unless(Auth::user()->can('Update:MeritPointRule'), 403);
        $rule = MeritPointRule::where('company_id', $this->companyId)
            ->where('trigger_type', MeritPointTriggerType::MANUAL->value)
            ->findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active]);
    }

    /** Modal "Tambah/Ubah Aturan" SELALU manual - tidak ada pilihan Cara Pemakaian lagi
     * (instruksi user 2026-09-27: rule yang dibuat admin lewat sini pasti manual, otomatis
     * cuma 4 baris tetap yang diisi lewat saveAutomaticRule()). */
    public function save(): void
    {
        abort_unless(Auth::user()->can($this->ruleId ? 'Update:MeritPointRule' : 'Create:MeritPointRule'), 403);

        // Livewire ->validate() tidak lewat middleware ConvertEmptyStringsToNull bawaan Laravel
        // (itu cuma jalan di request HTTP asli) - text input kosong ('') harus dikonversi manual
        // biar rule 'nullable|integer' benar-benar melewatkan field kosong, bukan menolaknya.
        if ($this->bonus_points === '') {
            $this->bonus_points = null;
        }

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::enum(MeritPointType::class)],
            'points' => 'required|integer',
            'bonus_points' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $data['trigger_type'] = MeritPointTriggerType::MANUAL->value;
        $data['event'] = null;
        // Poin bonus cuma relevan buat Reward - jangan simpan sisa angka lama kalau admin ganti
        // Tipe ke Penalty (field-nya disembunyikan di form, tapi property Livewire-nya tetap
        // kebawa kalau tidak dibersihkan di sini).
        if ($data['type'] !== MeritPointType::REWARD->value) {
            $data['bonus_points'] = null;
        }

        if ($this->ruleId) {
            MeritPointRule::where('company_id', $this->companyId)
                ->where('trigger_type', MeritPointTriggerType::MANUAL->value)
                ->findOrFail($this->ruleId)->update($data);
        } else {
            MeritPointRule::create($data + ['company_id' => $this->companyId]);
        }

        $this->showForm = false;
        Notification::make()->title('Aturan Merit Poin disimpan')->success()->send();
    }

    private function resetForm(): void
    {
        $this->reset(['ruleId', 'name', 'type', 'points', 'bonus_points', 'is_active']);
        $this->resetValidation();
    }

    /** Simpan 1 baris section "Otomatis" (MeritPointRule trigger_type=automatic, event=$eventValue) -
     * upsert langsung, tidak lewat modal. Nama baris sistem-generated dari label kejadian, bukan
     * diisi admin - baris ini bukan "aturan baru" tapi konfigurasi kejadian yang SUDAH tetap
     * (App\Enum\MeritPoint\MeritPointEvent), 1 baris per kejadian per company, tidak bisa
     * dobel/dihapus. */
    public function saveAutomaticRule(string $eventValue): void
    {
        $event = MeritPointEvent::from($eventValue);
        $canManageThis = $this->automaticForm[$eventValue]['id']
            ? Auth::user()->can('Update:MeritPointRule')
            : Auth::user()->can('Create:MeritPointRule');
        abort_unless($canManageThis, 403);

        // Sama alasan seperti save() rule manual - normalisasi '' -> null sebelum validasi.
        if (($this->automaticForm[$eventValue]['bonus_points'] ?? null) === '') {
            $this->automaticForm[$eventValue]['bonus_points'] = null;
        }

        $row = $this->validate([
            "automaticForm.{$eventValue}.type" => ['required', Rule::enum(MeritPointType::class)],
            "automaticForm.{$eventValue}.points" => ['required', 'integer'],
            "automaticForm.{$eventValue}.bonus_points" => ['nullable', 'integer'],
            "automaticForm.{$eventValue}.is_active" => ['boolean'],
        ])['automaticForm'][$eventValue];

        MeritPointRule::updateOrCreate(
            ['company_id' => $this->companyId, 'trigger_type' => MeritPointTriggerType::AUTOMATIC->value, 'event' => $eventValue],
            [
                'type' => $row['type'],
                'name' => 'Otomatis: ' . $event->getLabel(),
                'points' => $row['points'],
                // Sama seperti save() rule manual - poin bonus cuma relevan buat Reward.
                'bonus_points' => $row['type'] === MeritPointType::REWARD->value ? ($row['bonus_points'] ?? null) : null,
                'is_active' => (bool) ($row['is_active'] ?? false),
            ],
        );

        $this->loadAutomaticForm();

        Notification::make()->title('Poin otomatis ' . $event->getLabel() . ' disimpan')->success()->send();
    }

    public function render()
    {
        return view('livewire.kepegawaian.merit-point-page');
    }
}
