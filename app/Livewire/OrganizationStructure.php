<?php

namespace App\Livewire;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Illuminate\Support\Facades\Auth;
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
        $this->companyId = Auth::user()?->company_id ?? Companies::query()->first()?->id;
    }

    #[On('open-job-detail')]
    public function openJobDetail(string $jobId): void
    {
        $this->selectedJobId = $jobId;
        $this->selectedJobDetail = JobPositions::with(['orgStructure', 'parent', 'children', 'pangkat', 'kelas', 'userAssignments.user'])->find($jobId);
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function render()
    {
        return view('livewire.organization-structure');
    }
}
