@props(['model', 'selected' => '', 'options' => [], 'placeholder', 'icon', 'search' => false, 'align' => 'left'])

{{-- Dropdown filter kustom (pengganti <select>). Opsi dirender server-side supaya ikut ter-update Livewire
     (mis. daftar unit menyempit sesuai perusahaan); Alpine hanya mengurus buka/tutup & pencarian lokal.
     Nilai dikirim lewat $wire.$set. $options: list of ['v' => value, 'l' => label, 'c' => warna titik (opsional)]. --}}
@php($current = collect($options)->firstWhere('v', (string) $selected))

<div class="ps-fs" x-data="{ open: false, q: '' }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    <button type="button" class="ps-fs-btn @if ($current) on @endif" x-on:click="open = ! open; if (open) $nextTick(() => $refs.q?.focus())" aria-haspopup="listbox" x-bind:aria-expanded="open">
        <x-filament::icon :icon="$icon" class="ps-fs-ico" />
        <span class="ps-fs-label">{{ $current['l'] ?? $placeholder }}</span>
        @if ($current)
            <span class="ps-fs-clear" role="button" aria-label="Hapus filter" x-on:click.stop="$wire.$set('{{ $model }}', ''); open = false">
                <x-filament::icon icon="heroicon-m-x-mark" />
            </span>
        @else
            <x-filament::icon icon="heroicon-m-chevron-down" class="ps-fs-chev" />
        @endif
    </button>

    <div class="ps-fs-panel @if ($align === 'right') right @endif" x-show="open" x-cloak x-transition.opacity.duration.120ms>
        @if ($search)
            <div class="ps-fs-search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" />
                <input type="text" x-ref="q" x-model="q" placeholder="Cari…">
            </div>
        @endif
        <div class="ps-fs-opts" role="listbox">
            <button type="button" class="ps-fs-opt @if (! $current) sel @endif" x-show="! q" x-on:click="$wire.$set('{{ $model }}', ''); open = false; q = ''">
                {{ $placeholder }}
            </button>
            @foreach ($options as $o)
                <button type="button" class="ps-fs-opt @if ($current && $current['v'] === $o['v']) sel @endif"
                        x-show="! q || @js(mb_strtolower($o['l'])).includes(q.toLowerCase())"
                        x-on:click="$wire.$set('{{ $model }}', @js($o['v'])); open = false; q = ''">
                    @if (! empty($o['c']))<i class="ps-fs-dot" style="background: {{ $o['c'] }}"></i>@endif
                    <span>{{ $o['l'] }}</span>
                </button>
            @endforeach
        </div>
    </div>
</div>
