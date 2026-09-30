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
        {{-- Pemilih sesi. getInterviewRounds() = jumlah sesi yang sudah ada + satu slot kosong buat
             menjadwalkan sesi baru, jadi tombolnya bertambah sendiri tiap sesi baru dibuat. Tidak ada
             batas jumlah sesi, dan sesi TIDAK menentukan keputusan — keputusan ada di kolom Hasil,
             menempel di status pelamar. --}}
        @if($this->getInterviewRounds() > 1)
            {{-- Pakai komponen tabs bawaan Filament, bukan tombol rakitan sendiri: kelas warna
                 Tailwind seperti bg-primary-600 tidak ikut ter-build di CSS panel ini, jadi tombol
                 aktifnya sempat tampil tanpa latar dengan tulisan putih alias tak terbaca. --}}
            <x-filament::tabs label="Sesi wawancara" contained>
                @for($r = 1; $r <= $this->getInterviewRounds(); $r++)
                    <x-filament::tabs.item
                        :active="$this->currentRound() === $r"
                        :badge="$this->hasSession($r) ? null : 'baru'"
                        badge-color="gray"
                        wire:click="$set('activeRound', {{ $r }})">
                        {{ $this->roundLabel($r) }}
                    </x-filament::tabs.item>
                @endfor
            </x-filament::tabs>
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
