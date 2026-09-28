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
        {{-- Pemilih tahap — muncul kalau lowongannya lebih dari 1 tahap (Lowongan > Jumlah Tahap
             Wawancara). Jumlahnya tidak dibatasi: tombolnya ikut sebanyak tahap yang diatur. --}}
        @if($this->getInterviewRounds() > 1)
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Tahap:</span>
                @for($r = 1; $r <= $this->getInterviewRounds(); $r++)
                    <button type="button" wire:click="$set('activeRound', {{ $r }})"
                        @class([
                            'rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 transition',
                            'bg-primary-600 text-white ring-primary-600' => $this->currentRound() === $r,
                            'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-gray-700' => $this->currentRound() !== $r,
                        ])>
                        {{ $this->roundLabel($r) }}
                    </button>
                @endfor
            </div>
        @endif

        <div class="flex flex-wrap gap-3">
            @if($this->getReadyCount() > 0)
                {{ $this->jadwalWawancaraAction }}
            @endif
            @if($this->getInterviewDraft())
                {{ $this->publishInterviewAction }}
            @endif
            @if($this->getPublishedInterview())
                {{ $this->rescheduleWawancaraAction }}
            @endif
        </div>

        @for($round = 1; $round <= $this->getInterviewRounds(); $round++)
            @if($d = $this->getInterviewDraft($round))
                <div class="fi-section rounded-xl bg-primary-50 dark:bg-primary-950/30 ring-1 ring-primary-200 dark:ring-primary-800 px-6 py-3 text-xs text-primary-800 dark:text-primary-300">
                    <strong>Draft {{ $d->label() }}:</strong> {{ $d->interview_date?->translatedFormat('d M Y') }}
                    @if($d->interview_time) pukul {{ substr($d->interview_time, 0, 5) }} @endif
                    · {{ $d->invitees()->count() }} diundang — belum diterapkan.
                </div>
            @endif
        @endfor
    @endif

    {{ $this->table }}

</x-filament-panels::page>
