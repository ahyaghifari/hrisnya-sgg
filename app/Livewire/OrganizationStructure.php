<?php

namespace App\Livewire;

use App\Filament\Resources\Master\JobPositions\JobPositionResource;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Struktur Organisasi'])]
class OrganizationStructure extends Component
{
    public ?string $companyId = null;
    public ?string $selectedJobId = null;
    public ?object $selectedJobDetail = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('view_struktur_organisasi'), 403);

        $this->companyId = Auth::user()?->company_id ?? Companies::query()->first()?->id;
    }

    #[On('open-job-detail')]
    public function openJobDetail(string $jobId): void
    {
        $this->selectedJobId = $jobId;
        $this->selectedJobDetail = JobPositions::with(['orgStructure', 'parent', 'children', 'pangkat', 'golongan', 'kelas', 'userAssignments.user'])->find($jobId);
    }

    public function editJob(string $jobId): void
    {
        redirect()->to(JobPositionResource::getUrl('edit', ['record' => $jobId]));
    }

    public function getCompanyOptionsProperty()
    {
        $user = Auth::user();

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            return Companies::where('id', $user->company_id)->pluck('name', 'id');
        }

        return Companies::pluck('name', 'id');
    }

    public function getShowCompanyFilterProperty(): bool
    {
        $user = Auth::user();

        return !($user && $user->company_id && !PermissionHelper::hasGlobalRole($user));
    }

    public function render()
    {
        return view('livewire.organization-structure');
    }
}
