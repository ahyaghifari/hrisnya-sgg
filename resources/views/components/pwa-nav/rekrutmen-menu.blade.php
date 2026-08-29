@php
    $canMcu = auth()->user()->canInputSikartapMcu()
        || auth()->user()->can(\App\Enum\CustomPermissionEnum::EDIT_INPUT_MCU->value);
@endphp

<span class="pwa-menu-grid-divider">Rekrutmen</span>
<a href="{{ route('rekrutmen.lowongan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-briefcase"></i></span>
    <span class="pwa-menu-grid-label">Lowongan Kerja</span>
</a>
@if ($canMcu)
    <a href="{{ route('rekrutmen.mcu') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon gold"><i class="fa-solid fa-notes-medical"></i></span>
        <span class="pwa-menu-grid-label">Antrian MCU</span>
    </a>
@endif
