{{-- Bukan dropdown lagi — semua admin rekrutmen sekarang di panel Filament sendiri
     (/rekrutmen). Tanpa wire:navigate karena panel = shell aplikasi terpisah. --}}
<a href="{{ url('/rekrutmen') }}" class="nav-item nav-item-orange {{ request()->is('rekrutmen*') ? 'active' : '' }}">
    <i class="fa-solid fa-briefcase text-xs"></i> Rekrutmen
</a>
