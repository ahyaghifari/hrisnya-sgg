<?php

namespace App\Livewire\Pwa;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.pwa-layout', ['title' => 'Akun'])]
class Akun extends Component
{
    public User $record;

    public function mount(): void
    {
        $this->record = Auth::user()->load(['profile.jobPosition', 'profile.orgStructure.parent.parent']);
    }

    public function render()
    {
        return view('livewire.pwa.akun');
    }
}
