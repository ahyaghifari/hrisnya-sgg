@php
    $canMcu = auth()->user()->canInputSikartapMcu()
        || auth()->user()->can(\App\Enum\CustomPermissionEnum::EDIT_INPUT_MCU->value);
@endphp

<div class="dropdown">
    <a href="#" class="nav-item nav-item-orange {{ request()->routeIs('rekrutmen.*') ? 'active' : '' }}">
        <i class="fa-solid fa-briefcase text-xs"></i> Rekrutmen <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu">
        <a href="{{ route('rekrutmen.lowongan') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-briefcase"></i> Lowongan Kerja</a>
        @if ($canMcu)
            <a href="{{ route('rekrutmen.mcu') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-notes-medical"></i> Antrian MCU</a>
        @endif
    </div>
</div>
