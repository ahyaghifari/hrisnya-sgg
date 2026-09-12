@php
    $canViewRole = auth()->user()->can('ViewAny:Role');
@endphp

<span class="pwa-menu-grid-divider">Manajemen Pengguna</span>
@if ($canViewRole)
    <a href="{{ route('role') }}" wire:navigate class="pwa-menu-grid-item">
        <span class="pwa-menu-grid-icon sky"><i class="fa-solid fa-user-tag"></i></span>
        <span class="pwa-menu-grid-label">Role</span>
    </a>
@endif
<a href="{{ route('hak-akses') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon sky"><i class="fa-solid fa-shield-halved"></i></span>
    <span class="pwa-menu-grid-label">Hak Akses</span>
</a>
<a href="{{ route('akses-filter-perusahaan') }}" wire:navigate class="pwa-menu-grid-item">
    <span class="pwa-menu-grid-icon sky"><i class="fa-solid fa-building-shield"></i></span>
    <span class="pwa-menu-grid-label">Akses Filter Perusahaan</span>
</a>
