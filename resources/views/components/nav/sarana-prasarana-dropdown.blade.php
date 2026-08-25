@php
    $canAjukanSarpras = auth()->user()->can(\App\Enum\CustomPermissionEnum::AJUKAN_SARANA_PRASARANA->value);
@endphp

<div class="dropdown">
    <a href="#" class="nav-item nav-item-navy {{ request()->routeIs(['sarana-prasarana*']) ? 'active' : '' }}">
        <i class="fa-solid fa-toolbox text-xs"></i> Sarana & Prasarana <i class="fa-solid fa-chevron-down text-[10px]"></i>
    </a>
    <div class="dropdown-menu">
        @if ($canAjukanSarpras)
            <a href="{{ route('sarana-prasarana.ajukan') }}" wire:navigate class="dropdown-item">
                <i class="fa-solid fa-file-circle-plus"></i> Ajukan Permohonan
            </a>
        @endif
        <a href="{{ route('sarana-prasarana') }}" wire:navigate class="dropdown-item">
            <i class="fa-solid fa-list-check"></i> Daftar Permohonan
        </a>
    </div>
</div>
