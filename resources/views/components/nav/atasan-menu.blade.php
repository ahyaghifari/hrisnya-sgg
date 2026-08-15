<a href="{{ route('my-staff') }}" wire:navigate class="nav-item nav-item-navy {{ request()->routeIs('my-staff*') ? 'active' : '' }}">
    <i class="fa-solid fa-users text-xs"></i> MyStaff
</a>
<a href="{{ route('riwayat-approval') }}" wire:navigate class="nav-item nav-item-orange {{ request()->routeIs('riwayat-approval*') ? 'active' : '' }}">
    <i class="fa-solid fa-clock-rotate-left text-xs"></i> Riwayat Approval
</a>
