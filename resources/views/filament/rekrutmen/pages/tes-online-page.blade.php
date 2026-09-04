<x-filament-panels::page>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Perusahaan</label>
                <select wire:model.live="companyId"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                    <option value="">— Pilih perusahaan —</option>
                    @foreach($this->getCompanies() as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Lowongan</label>
                <select wire:model.live="jobOpeningId" @if(!$this->companyId) disabled @endif
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm disabled:opacity-50">
                    <option value="">— Pilih lowongan —</option>
                    @foreach($this->getJobOpenings() as $opening)
                        <option value="{{ $opening->id }}">{{ $opening->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if($this->jobOpeningId)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm font-bold text-gray-900 dark:text-gray-100">Kuis Lowongan Ini</div>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @forelse($this->getSelectedJobOpening()?->quizzes ?? [] as $quiz)
                        <x-filament::badge color="success">{{ $quiz->name }}</x-filament::badge>
                    @empty
                        <span class="text-xs text-gray-400">Belum ada kuis dipasang — tes belum bisa diaktifkan.</span>
                    @endforelse
                </div>
                @if($this->isQuizLocked())
                    <div class="mt-2 text-xs text-amber-600 dark:text-amber-400">Terkunci — tes sudah diaktifkan.</div>
                @endif
            </div>
            {{ $this->pilihKuisAction }}
        </div>

        @if($this->hasQuizAssigned() && $this->getBelumDiaktifkanCount() > 0)
        <div class="fi-section rounded-xl bg-sky-50 dark:bg-sky-950/30 ring-1 ring-sky-200 dark:ring-sky-800 px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm font-bold text-sky-900 dark:text-sky-100">Siap Diaktifkan Tesnya</div>
                <div class="text-xs text-sky-700 dark:text-sky-400 mt-0.5">
                    <strong>{{ $this->getBelumDiaktifkanCount() }}</strong> kandidat lolos berkas belum diaktifkan tesnya.
                </div>
            </div>
            {{ $this->aktifkanTesAction }}
        </div>
        @endif
    @endif

    {{ $this->table }}

</x-filament-panels::page>
