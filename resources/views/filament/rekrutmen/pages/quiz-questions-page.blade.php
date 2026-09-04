<x-filament-panels::page>

    <div class="-mt-2 mb-2">
        <a href="{{ route('filament.rekrutmen.resources.quizzes.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 no-underline">
            <x-heroicon-m-arrow-left class="w-4 h-4" /> Kembali ke Kuis
        </a>
    </div>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Kuis</label>
        <select wire:model.live="quizId"
            class="w-full max-w-md rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
            <option value="">— Pilih kuis —</option>
            @foreach($this->getQuizzes() as $quiz)
                <option value="{{ $quiz->id }}">{{ $quiz->name }}</option>
            @endforeach
        </select>
    </div>

    @if($quizId)
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button wire:click="switchMode('table')" :color="$mode === 'table' ? 'primary' : 'gray'" size="sm">
                Lihat / Urutkan
            </x-filament::button>
            <x-filament::button wire:click="switchMode('edit')" :color="$mode === 'edit' ? 'primary' : 'gray'" size="sm">
                Kelola Soal (Editor)
            </x-filament::button>
        </div>

        @if($mode === 'edit')
            <form wire:submit="save" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 space-y-4">
                {{ $this->form }}
                <x-filament::button type="submit">Simpan Semua Soal</x-filament::button>
            </form>
        @else
            {{ $this->table }}
        @endif
    @else
        <p class="text-sm text-gray-500">Pilih kuis di atas untuk mengelola soalnya.</p>
    @endif

</x-filament-panels::page>
