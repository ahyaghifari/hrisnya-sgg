<?php

namespace App\Livewire\Pwa;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.pwa-layout', ['title' => 'Beranda'])]
class Home extends Component
{
    public function render()
    {
        return view('livewire.pwa.home');
    }
}
