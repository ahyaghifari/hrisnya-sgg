@props(['variant' => 'icon'])

@if ($variant === 'bar')
    <button type="button" class="pwa-app-bar-bell theme-toggle-icons" onclick="toggleTheme()" aria-label="Ganti tema gelap/terang">
        <i class="fa-solid fa-moon"></i>
        <i class="fa-solid fa-sun"></i>
    </button>
@else
    <button type="button" class="theme-toggle-btn theme-toggle-icons" onclick="toggleTheme()" aria-label="Ganti tema gelap/terang">
        <i class="fa-solid fa-moon"></i>
        <i class="fa-solid fa-sun"></i>
    </button>
@endif
