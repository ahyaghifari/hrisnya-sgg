{{-- Modul MCU berdiri sendiri (2026-09-08) — payung antrian input hasil MCU dari 2 sumber
     (Sikartap & Rekrutmen). Gerbang: User::canAccessMcuModule() (SISTEM_MCU / INPUT_MCU_*),
     lepas dari SISTEM_SIKARTAP / SISTEM_REKRUTMEN. --}}
<div class="dropdown">
    <a href="#" style="background:#98782d" class="nav-item nav-item-teal {{ request()->routeIs('sikartap.mcu.*') || request()->routeIs('rekrutmen.mcu.*') ? 'active' : '' }}">
        <i class="fa-solid fa-stethoscope text-xs"></i> MCU <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu">
        <a href="{{ route('sikartap.mcu.queue') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-user-check"></i> Antrian Sikartap</a>
        <a href="{{ route('rekrutmen.mcu.queue') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-briefcase"></i> Antrian Rekrutmen</a>
    </div>
</div>
