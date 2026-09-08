{{-- Tab tahap kandidat — di bawah selektor Perusahaan + Lowongan. Pindah tab tidak
     mereset pilihan (companyId/jobOpeningId lewat #[Url] + session). --}}
<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-1.5 overflow-x-auto">
    <nav class="flex gap-1 min-w-max">
        @foreach ($this->pipelineTabs() as $tab)
            <a href="{{ $tab['url'] }}" wire:navigate
               @class([
                   'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition',
                   'bg-gray-100 text-gray-900 ring-1 ring-inset ring-gray-300 dark:bg-white/10 dark:text-white dark:ring-white/15' => $tab['active'],
                   'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200' => ! $tab['active'],
               ])>
                <x-filament::icon :icon="$tab['icon']" class="h-4 w-4 shrink-0" />
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
