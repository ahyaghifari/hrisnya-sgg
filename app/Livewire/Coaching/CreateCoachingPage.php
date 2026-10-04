<?php

namespace App\Livewire\Coaching;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Coaching\CoachingService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Form Buat Coaching baru — padanan CreateCoaching admin, satu langkah langsung isi semua
 * field (status jadi IN_PROGRESS), bukan lewat state PENDING dulu. Dua jalur akses beda scope
 * karyawan (sama seperti CoachingPage, instruksi user 2026-07-26): permission `Create:Coaching`
 * (HC) -> semua karyawan company; sekadar punya staff -> cuma staff-nya sendiri.
 *
 * Coaching & SP sengaja dipisah total (instruksi user 2026-09-23) — form ini TIDAK ada urusan SP
 * sama sekali. SP dibuat dari sisi SP sendiri (CreateSpPage/EmployeeSpResource), yang punya field
 * opsional "sumber dari Coaching mana" kalau relevan.
 */
#[Layout('components.layout', ['title' => 'Buat Coaching'])]
class CreateCoachingPage extends Component
{
    public ?string $companyId = null;
    public ?string $user_id = null;
    public ?string $coach_by = null;
    public string $title = '';
    public ?string $improvement_due_date = null;
    public string $goals = '';
    public string $employee_condition = '';
    public string $solution = '';
    public string $improvement_action = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);

        abort_unless(
            Auth::user()->can('Create:Coaching')
                || Auth::user()->can(CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value)
                || Auth::user()->canAccessKaryawanMenu(),
            403
        );

        if (! $this->canBrowseAllCompanies) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->coach_by = Auth::id();
        $this->improvement_due_date = today()->toDateString();

        // Prefill dari tombol pintasan "Buat Coaching Lanjutan" di CoachingDetailPage
        // (planning/2026-09-23-sp-dan-merit-point.md §1.1) - cuma kenyamanan UI, scope
        // staff/company tetap dicek ulang di save() saat submit.
        if ($prefillUserId = request()->query('user_id')) {
            $prefillUser = User::find($prefillUserId);

            if ($prefillUser) {
                if ($this->canBrowseAllCompanies) {
                    $this->companyId = $prefillUser->company_id;
                }

                $this->user_id = $prefillUser->id;
            }
        }
    }

    public function getHasFullAccessProperty(): bool
    {
        return Auth::user()->can('Create:Coaching')
            || Auth::user()->can(CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value);
    }

    public function updatedCompanyId(): void
    {
        if (! $this->canBrowseAllCompanies) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->user_id = null;
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    /** Sama seperti CoachingPage — filter company cuma buat hasFullAccess, subordinate-only
     * selalu terkunci ke company sendiri. */
    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return $this->hasFullAccess && Auth::user()->canFilterByCompany();
    }

    public function getEmployeeOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        $query = User::whereCompany($this->companyId);

        if (! $this->hasFullAccess) {
            $subordinateIds = app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');
            $query->whereIn('id', $subordinateIds);
        }

        return $query->orderByName()->get()->pluck('name', 'id');
    }

    /**
     * Dropdown "Coach" — beda dari "Karyawan" di atas: yang lewat jalur canAccessKaryawanMenu()
     * (bukan permission Create:Coaching) HARUS bisa pilih dirinya sendiri sebagai coach (dia
     * BUKAN staff-nya sendiri, jadi tidak ikut ke-scope subordinates() seperti employeeOptions).
     */
    public function getCoachOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        if ($this->hasFullAccess) {
            return User::whereCompany($this->companyId)->orderByName()->get()->pluck('name', 'id');
        }

        $subordinateIds = app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');

        return User::whereCompany($this->companyId)
            ->where(fn ($q) => $q->whereIn('id', $subordinateIds)->orWhere('id', Auth::id()))
            ->orderByName()
            ->get()->pluck('name', 'id');
    }

    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'coach_by' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'improvement_due_date' => ['required', 'date', 'after_or_equal:today'],
            'goals' => ['required', 'string'],
            'employee_condition' => ['required', 'string'],
            'solution' => ['required', 'string'],
            'improvement_action' => ['required', 'string'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        // Dropdown "Karyawan"/"Coach" sudah di-scope ke staff buat yang lewat jalur
        // canAccessKaryawanMenu() (getEmployeeOptionsProperty()/getCoachOptionsProperty()), tapi
        // itu cuma UI — cek ulang di server biar tidak bisa ditembus lewat wire:model manual buat
        // bikin coaching atas nama karyawan/coach di luar staff-nya.
        if (! $this->hasFullAccess) {
            $subordinateIds = app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');
            abort_unless($subordinateIds->contains($data['user_id']), 403);
            abort_unless($subordinateIds->contains($data['coach_by']) || $data['coach_by'] === Auth::id(), 403);
        }

        $data['company_id'] = $this->companyId ?? Auth::user()->company_id;

        $coaching = app(CoachingService::class)->create($data);

        Notification::make()->title('Coaching berhasil dibuat')->success()->send();

        $this->redirect(route('coaching.show', $coaching), navigate: true);
    }

    public function render()
    {
        return view('livewire.coaching.create-coaching-page');
    }
}
