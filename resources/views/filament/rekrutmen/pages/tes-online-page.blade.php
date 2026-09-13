<x-filament-panels::page>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Perusahaan</label>
                <select wire:model.live="companyId" @disabled(! $this->canFilterByCompany())
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

    @include('filament.rekrutmen.pages.partials.pipeline-tabs')

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

        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm font-bold text-gray-900 dark:text-gray-100">Tes Offline Lowongan Ini</div>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @forelse($this->getSelectedJobOpening()?->offlineTests ?? [] as $test)
                        <x-filament::badge color="warning">{{ $test->name }}</x-filament::badge>
                    @empty
                        <span class="text-xs text-gray-400">Lowongan ini tidak pakai tes offline.</span>
                    @endforelse
                </div>
            </div>
            {{ $this->pilihTesOfflineAction }}
        </div>

        @if($this->hasOfflineTestAssigned())
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-6 py-4">
            <div class="flex flex-wrap items-center gap-3 mb-3">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Jadwalkan tes offline:</label>
                <select wire:model.live="scheduleTestId"
                    class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                    <option value="">— Pilih tes —</option>
                    @foreach($this->getSelectedJobOpening()?->offlineTests ?? [] as $test)
                        <option value="{{ $test->id }}">{{ $test->name }}</option>
                    @endforeach
                </select>
            </div>

            @if($this->scheduleTestId)
                @php $offlinePublished = $this->getPublishedOfflineTest(); @endphp
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        @if($offlinePublished)
                            Jadwal terbit: <strong>{{ $offlinePublished->test_date?->translatedFormat('l, j F Y') }}</strong>
                            @if($offlinePublished->test_time) &middot; {{ substr($offlinePublished->test_time, 0, 5) }} WIB @endif
                            @if($offlinePublished->location) &middot; {{ $offlinePublished->location }} @endif
                        @else
                            Belum ada jadwal terbit untuk tes ini.
                        @endif
                    </div>
                    <div class="flex gap-2">
                        {{ $this->jadwalTesOfflineAction }}
                        {{ $this->terapkanJadwalOfflineAction }}
                        {{ $this->rescheduleSemuaOfflineAction }}
                    </div>
                </div>
            @endif
        </div>
        @endif

        @if($this->hasQuizAssigned() && $this->getBelumDiaktifkanCount() > 0)
        <div class="fi-section rounded-xl bg-primary-50 dark:bg-primary-950/30 ring-1 ring-primary-200 dark:ring-primary-800 px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm font-bold text-primary-900 dark:text-primary-100">Siap Diaktifkan Tesnya</div>
                <div class="text-xs text-primary-700 dark:text-primary-400 mt-0.5">
                    <strong>{{ $this->getBelumDiaktifkanCount() }}</strong> kandidat lolos berkas belum diaktifkan tesnya.
                </div>
            </div>
            {{ $this->aktifkanTesAction }}
        </div>
        @endif
    @endif

    {{ $this->table }}

</x-filament-panels::page>
