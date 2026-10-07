<?php

namespace App\Livewire\Pembinaan;

use Livewire\Attributes\Layout;
use Livewire\Component;

/** Halaman "Pembinaan Saya" — pembungkus PembinaanSayaList (khusus milik user yang login). */
#[Layout('components.layout', ['title' => 'Pembinaan Saya'])]
class PembinaanSayaPage extends Component
{
    public function render()
    {
        return view('livewire.pembinaan.pembinaan-saya-page');
    }
}
