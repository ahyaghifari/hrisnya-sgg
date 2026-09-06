<?php

namespace App\Livewire\Pwa;

use App\Livewire\Concerns\HandlesProfilePhotoUpload;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Akun'])]
class Akun extends Component
{
    use HandlesProfilePhotoUpload;

    public User $record;

    public function mount(): void
    {
        $this->record = Auth::user()->load(['roles', 'profile.jobPosition', 'profile.orgStructure.parent.parent']);
    }

    public function render()
    {
        return view('livewire.pwa.akun');
    }
}
