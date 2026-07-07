<?php

namespace App\Livewire\Pwa;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.pwa-layout', ['title' => 'Data Diri'])]
class DataDiri extends Component
{
    public User $record;

    public function mount(): void
    {
        $this->record = Auth::user()->load([
            'company',
            'roles',
            'profile.religion',
            'profile.bank',
            'profile.orgStructure.parent.parent',
            'profile.jobPosition',
            'profile.pangkat',
            'profile.golongan',
            'profile.kelas',
            'profile.employeeType',
        ]);
    }

    public function render()
    {
        return view('livewire.pwa.data-diri');
    }
}
