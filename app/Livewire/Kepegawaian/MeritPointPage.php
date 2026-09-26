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
    public string $type = 'penalty';
    public int|string $points = 0;
    public string $trigger_type = 'manual';
    public ?string $event = null;
    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:MeritPointRule') || Auth::user()->can('ViewAny:MeritPointTransaction'), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
    }

    public function updatedCompanyId(): void
    {
        // Property Livewire bisa di-$set langsung dari request - user non-global tidak boleh pindah company.
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
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
            ->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId))
            ->orderBy('merit_point')
            ->limit(10)
            ->get();
    }

    public function getSummaryProperty(): array
    {
        $tx = MeritPointTransaction::whereHas('user', fn ($q) => $q->where('company_id', $this->companyId))
            ->where('created_at', '>=', now()->startOfMonth());

        return [
            'karyawan' => UserHrBalance::whereHas('user', fn ($q) => $q->where('company_id', $this->companyId))->where('merit_point', '<', 100)->count(),
            'penalti' => (int) (clone $tx)->where('points', '<', 0)->sum('points'),
            'reward' => (int) (clone $tx)->where('points', '>', 0)->sum('points'),
        ];
    }

    public function getRecentTransactionsProperty()
    {
        return MeritPointTransaction::with(['user', 'rule'])
            ->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId))
            ->latest('created_at')
            ->limit(15)
            ->get();
    }

    public function getRulesProperty()
    {
        return MeritPointRule::where('company_id', $this->companyId)
            ->orderByDesc('is_active')->orderBy('trigger_type')->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        abort_unless(Auth::user()->can('Create:MeritPointRule'), 403);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        abort_unless(Auth::user()->can('Update:MeritPointRule'), 403);
        $rule = MeritPointRule::where('company_id', $this->companyId)->findOrFail($id);

        $this->ruleId = $rule->id;
        $this->name = $rule->name;
        $this->type = $rule->type->value;
        $this->points = $rule->points;
        $this->trigger_type = $rule->trigger_type->value;
        $this->event = $rule->event?->value;
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
        $rule = MeritPointRule::where('company_id', $this->companyId)->findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active]);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can($this->ruleId ? 'Update:MeritPointRule' : 'Create:MeritPointRule'), 403);

        $auto = $this->trigger_type === MeritPointTriggerType::AUTOMATIC->value;

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::enum(MeritPointType::class)],
            'points' => 'required|integer',
            'trigger_type' => ['required', Rule::enum(MeritPointTriggerType::class)],
            'event' => [$auto ? 'required' : 'nullable', Rule::enum(MeritPointEvent::class)],
            'is_active' => 'boolean',
        ]);

        if (! $auto) {
            $data['event'] = null;
        }

        // Satu rule aktif per kejadian per company - kalau dobel, sistem cuma memakai yang pertama ketemu.
        if ($auto && $data['is_active'] && MeritPointRule::where('company_id', $this->companyId)
            ->where('trigger_type', 'automatic')->where('event', $data['event'])->where('is_active', true)
            ->when($this->ruleId, fn ($q) => $q->where('id', '!=', $this->ruleId))->exists()) {
            $this->addError('event', 'Sudah ada rule otomatis aktif untuk kejadian ini. Nonaktifkan dulu yang lama.');

            return;
        }

        if ($this->ruleId) {
            MeritPointRule::where('company_id', $this->companyId)->findOrFail($this->ruleId)->update($data);
        } else {
            MeritPointRule::create($data + ['company_id' => $this->companyId]);
        }

        $this->showForm = false;
        Notification::make()->title('Aturan Merit Poin disimpan')->success()->send();
    }

    private function resetForm(): void
    {
        $this->reset(['ruleId', 'name', 'type', 'points', 'trigger_type', 'event', 'is_active']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.kepegawaian.merit-point-page');
    }
}
