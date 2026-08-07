@php
    $canAccessCoachingCounseling = auth()->user()->canAccessKaryawanMenu();
@endphp

<a href="{{ route('my-staff') }}" wire:navigate class="nav-item nav-item-navy {{ request()->routeIs('my-staff*') ? 'active' : '' }}">
    <i class="fa-solid fa-users text-xs"></i> MyStaff
</a>
<a href="{{ route('riwayat-approval') }}" wire:navigate class="nav-item nav-item-orange {{ request()->routeIs('riwayat-approval*') ? 'active' : '' }}">
    <i class="fa-solid fa-clock-rotate-left text-xs"></i> Riwayat Approval
</a>

@if ($canAccessCoachingCounseling)
    <div class="dropdown">
        <a href="#" class="nav-item nav-item-indigo {{ request()->routeIs(['coaching', 'coaching.create', 'coaching.show', 'counseling.kelola*']) ? 'active' : '' }}">
            <i class="fa-solid fa-comment-dots text-xs"></i> Coaching & Counseling <i class="fa-solid fa-chevron-down text-[10px]"></i>
        </a>
        <div class="dropdown-menu">
            <a href="{{ route('coaching') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-clipboard-check"></i> Coaching</a>
            <a href="{{ route('counseling.kelola') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-comments"></i> Counseling</a>
        </div>
    </div>
@endif
