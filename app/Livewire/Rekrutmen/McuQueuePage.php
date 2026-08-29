<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\CustomPermissionEnum;
use App\Services\Job\RekrutmenSyncService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Antrian MCU Rekrutmen — semua pelamar status `mcu` lintas lowongan, dari API Rekrutmen.
 * Read + link ke McuFormPage. Gate: punya salah satu permission INPUT_MCU_* / EDIT_INPUT_MCU
 * (dipakai bareng modul Sikartap).
 */
#[Layout('components.layout', ['title' => 'Antrian MCU (Rekrutmen)'])]
class McuQueuePage extends Component
{
    public function mount(): void
    {
        abort_unless(
            Auth::user()->canInputSikartapMcu() || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value),
            403,
        );
    }

    public function getAntrianProperty(): array
    {
        return app(RekrutmenSyncService::class)->getMcuQueue();
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu-queue-page');
    }
}
