<?php

namespace App\Livewire\Pelatihan;

use App\Models\LMS\TrainingClaim;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layout', ['title' => 'Klaim Pelatihan'])]
class MyTrainingClaims extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    public function getClaimsProperty()
    {
        return TrainingClaim::query()
            ->with('sessions')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.pelatihan.my-training-claims');
    }
}
