<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpRecommendationDecision;
use App\Models\Master\Companies;
use App\Models\Sp\SpRecommendation;
use App\Services\Sp\SpService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Review rekomendasi SP dari Coaching — portal, padanan halaman review di
 * SpRecommendationResource Filament (planning/2026-09-23-sp-dan-merit-point.md §2.4), versi
 * mobile-friendly buat HC yang kerja dari HP.
 */
#[Layout('components.layout', ['title' => 'Review Rekomendasi SP'])]
class SpRecommendationsPage extends Component
{
    use WithPagination;

    #[Url]
    public string $decision = 'pending';

    #[Url]
    public ?string $companyId = null;

    public ?string $actioningId = null;
    public ?string $actionType = null;
    public ?string $approveLevel = null;
    public ?string $approveStartDate = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('ViewAny:SpRecommendation'), 403);

        if (! $this->canBrowseAllCompanies) {
            $this->companyId = $user->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = $user->company_id;
        }
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function updatedCompanyId(): void
    {
        if (! $this->canBrowseAllCompanies) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->resetPage();
    }

    public function updatedDecision(): void
    {
        $this->resetPage();
    }

    public function getDecisionOptionsProperty(): array
    {
        return collect(SpRecommendationDecision::cases())->mapWithKeys(fn ($d) => [$d->value => $d->getLabel()])->all();
    }

    public function getSpLevelOptionsProperty(): array
    {
        return collect(SpLevel::cases())->mapWithKeys(fn ($level) => [$level->value => $level->getLabel()])->all();
    }

    public function getRecommendationsProperty()
    {
        return SpRecommendation::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->decision, fn ($q) => $q->where('decision', $this->decision))
            ->with(['user', 'reason'])
            ->latest()
            ->paginate(10)
            ->appends(['decision' => $this->decision, 'companyId' => $this->companyId]);
    }

    public function chooseAction(string $id, string $type): void
    {
        abort_unless(Auth::user()->can('Update:SpRecommendation'), 403);

        $rec = SpRecommendation::findOrFail($id);

        $this->actioningId = $id;
        $this->actionType = $type;
        $this->approveLevel = $rec->sp_level->value;
        $this->approveStartDate = today()->toDateString();
        $this->resetErrorBag();
    }

    public function cancelAction(): void
    {
        $this->actioningId = null;
        $this->actionType = null;
        $this->approveLevel = null;
        $this->approveStartDate = null;
    }

    public function confirmAction(): void
    {
        abort_unless(Auth::user()->can('Update:SpRecommendation'), 403);

        $rec = SpRecommendation::findOrFail($this->actioningId);
        abort_unless($rec->decision === SpRecommendationDecision::PENDING, 403);

        if ($this->actionType === 'approve') {
            $this->validate([
                'approveLevel' => ['required', 'in:' . implode(',', array_column(SpLevel::cases(), 'value'))],
                'approveStartDate' => ['required', 'date'],
            ]);

            app(SpService::class)->approveRecommendation(
                $rec,
                SpLevel::from($this->approveLevel),
                $this->approveStartDate,
                Auth::id()
            );

            Notification::make()->title('SP disetujui dan diterbitkan')->success()->send();
        } else {
            app(SpService::class)->rejectRecommendation($rec, Auth::id());

            Notification::make()->title('Rekomendasi ditolak')->success()->send();
        }

        $this->cancelAction();
    }

    public function render()
    {
        return view('livewire.sp.sp-recommendations-page');
    }
}
