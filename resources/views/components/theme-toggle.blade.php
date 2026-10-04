@props(['variant' => 'icon'])

{{-- Dropdown tampilan: (1) saklar mode terang/gelap, (2) pilihan warna tema. Keduanya client-side murni
     (localStorage + class/atribut di <html>, lihat toggleTheme()/setAccent() di layout.blade.php). Klik, bukan hover,
     supaya jalan di HP. --}}
<div class="theme-menu" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <button type="button" x-on:click="open = ! open" :aria-expanded="open" aria-label="Tampilan: mode gelap dan warna tema"
        class="{{ $variant === 'bar' ? 'pwa-app-bar-bell' : 'theme-toggle-btn' }}">
        <i class="fa-solid fa-palette"></i>
    </button>

    <div class="theme-menu-panel" x-show="open" x-cloak x-transition.opacity.duration.100ms>
        <button type="button" class="theme-menu-row" onclick="toggleTheme()">
            <span style="display:flex;align-items:center;gap:10px;"><i class="fa-solid fa-circle-half-stroke"></i> Mode Gelap</span>
            <span class="theme-switch"><span class="theme-switch-knob"></span></span>
        </button>

        <div class="theme-menu-divider"></div>

        <p class="theme-menu-label">Warna Tema</p>
        <div class="accent-picker" style="display:flex;gap:12px;padding:0 12px 10px;">
            @foreach (['biru' => ['#042c6c', 'Biru'], 'hijau' => ['#047C54', 'Hijau'], 'pink' => ['#a52a7a', 'Pink'], 'ungu' => ['#6e1a8c', 'Ungu'], 'emas' => ['#8f6d1c', 'Emas']] as $key => [$hex, $label])
                <button type="button" data-accent="{{ $key }}" onclick="pickAccent('{{ $key }}')" class="accent-swatch" style="--sw:{{ $hex }};" aria-label="{{ $label }}"></button>
            @endforeach
        </div>
    </div>
</div>
