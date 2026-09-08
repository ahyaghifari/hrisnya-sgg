<div class="space-y-6">
    {{-- Ringkasan jumlah kandidat per tahap --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach ($stats as $s)
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 p-4 text-center"
                 style="border-top: 3px solid {{ $s['color'] }}">
                <div class="text-2xl font-bold" style="color: {{ $s['color'] }}">{{ $s['count'] }}</div>
                <div class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400 leading-tight">{{ $s['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Aksi cepat ke halaman pipeline --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Aksi Cepat</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" wire:navigate
                   class="fi-section group rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 p-5 flex items-start gap-4 transition hover:ring-2"
                   style="border-left: 4px solid {{ $link['color'] }}">
                    <span class="shrink-0 rounded-lg p-2.5" style="color: {{ $link['color'] }}; background-color: {{ $link['color'] }}1a">
                        <x-filament::icon :icon="$link['icon']" class="h-6 w-6" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-semibold text-gray-900 dark:text-white">{{ $link['label'] }}</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $link['description'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</div>
