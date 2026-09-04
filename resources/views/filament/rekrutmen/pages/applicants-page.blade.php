<x-filament-panels::page>

    {{-- Selektor --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Filter Status</label>
                <select wire:model.live="statusFilter"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
                    <option value="">Semua</option>
                    <option value="applied">Lamaran Masuk</option>
                    <option value="screening">Peninjauan Berkas</option>
                    <option value="shortlisted">Lolos Seleksi Berkas</option>
                    <option value="rejected">Tidak Dilanjutkan</option>
                </select>
            </div>
        </div>
    </div>

    @if($this->jobOpeningId)
        {{-- Statistik --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            @foreach($this->getStats() as $stat)
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                    <div class="text-3xl font-extrabold" style="color:{{ $stat['color'] }}">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Banner aksi --}}
        <div class="flex flex-wrap gap-3">
            @if($this->getAppliedCount() > 0)
                {{ $this->bulkMoveToScreeningAction }}
            @endif
            @if($this->getScreeningCount() > 0)
                {{ $this->seleksiBerkasAction }}
            @endif
            @if($this->getDraftDecisionCount() > 0)
                {{ $this->publishScreeningAction }}
            @endif
        </div>

        @if($this->getDraftDecisionCount() > 0)
            <div class="fi-section rounded-xl bg-emerald-50 dark:bg-emerald-950/30 ring-1 ring-emerald-200 dark:ring-emerald-800 px-6 py-3 text-xs text-emerald-800 dark:text-emerald-300">
                <strong>{{ $this->getDraftDecisionCount() }}</strong> keputusan seleksi tersimpan sebagai draft — pelamar belum melihat perubahan. Klik <strong>Terapkan Keputusan</strong>.
            </div>
        @endif
    @endif

    {{ $this->table }}

</x-filament-panels::page>
