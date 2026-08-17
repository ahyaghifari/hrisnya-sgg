<?php

namespace App\Livewire\Coaching;

use App\Enum\CoachingStatus;
use App\Enum\CustomPermissionEnum;
use App\Models\Coaching\Coaching;
use App\Models\Master\Companies;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Coaching" — dua jalur akses beda scope (instruksi user 2026-07-26):
 * - Permission Shield `ViewAny:Coaching` ATAU custom `COACHING_KARYAWAN_LAINNYA` (role HC
 *   khusus, dua-duanya berarti "boleh mengcoaching siapa saja") -> company-wide, semua karyawan.
 * - Sekadar punya bawahan (`canAccessKaryawanMenu()`, sama gate MyStaff) -> di-scope ke
 *   bawahannya sendiri (`OrgHierarchyService::subordinates()`) UNION `coach_by = auth()->id()`,
 *   BUKAN company-wide — supaya atasan biasa tidak intip data karyawan yang bukan tanggung
 *   jawabnya.
 */
#[Layout('components.layout', ['title' => 'Coaching'])]
class CoachingPage extends Component
{
    use WithPagination;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $user = Auth::user();

        // Ditunjuk jadi coach di minimal 1 record cukup buat buka list ini SENDIRI (scoped ke
        // baris dia doang lewat baseQuery() `orWhere('coach_by', ...)` di bawah) - tidak perlu
        // SISTEM_PEMBINAAN_KARYAWAN (instruksi user 2026-08-17, sama alasan CoachingDetailPage).
        $isCoachSomewhere = Coaching::where('coach_by', $user->id)->exists();

        abort_unless(
            $user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value)
                || $user->canAccessKaryawanMenu()
                || $isCoachSomewhere,
            403
        );

        abort_unless(
            $user->can('ViewAny:Coaching')
                || $user->can(CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value)
                || $user->canAccessKaryawanMenu()
                || $isCoachSomewhere,
            403
        );

        if (! $this->canBrowseAllCompanies) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getHasFullAccessProperty(): bool
    {
        return Auth::user()->can('ViewAny:Coaching')
            || Auth::user()->can(CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value);
    }

    public function updatedCompanyId(): void
    {
        if (! $this->canBrowseAllCompanies) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    /**
     * Filter perusahaan cuma relevan buat yang company-wide (hasFullAccess) — subordinate-only
     * TETAP dikunci ke company sendiri walau kebetulan juga punya permission LIHAT_PERUSAHAAN_LAIN
     * (permission itu independen, tidak seharusnya buka filter company di sini juga, karena
     * baseQuery() tetap scoped ke bawahan berapa pun company yang dipilih — dulu jadi dead-end
     * dropdown kosong kalau pilih company lain).
     */
    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return $this->hasFullAccess && Auth::user()->canFilterByCompany();
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('Create:Coaching')
            || Auth::user()->can(CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value)
            || Auth::user()->canAccessKaryawanMenu();
    }

    public function getStatusesProperty(): array
    {
        return CoachingStatus::cases();
    }

    protected function baseQuery()
    {
        $search = trim($this->search);
        $user = Auth::user();

        return Coaching::query()
            ->when(! $this->hasFullAccess, function ($q) use ($user) {
                $subordinateIds = app(OrgHierarchyService::class)->subordinates($user)->pluck('id');

                $q->where(fn ($q2) => $q2
                    ->whereIn('user_id', $subordinateIds)
                    ->orWhere('coach_by', $user->id));
            })
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with(['user', 'coachBy']);
    }

    public function getCoachingsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10)->appends([
            'companyId' => $this->companyId,
            'status' => $this->status,
            'search' => $this->search,
        ]);
    }

    public function render()
    {
        return view('livewire.coaching.coaching-page');
    }
}
