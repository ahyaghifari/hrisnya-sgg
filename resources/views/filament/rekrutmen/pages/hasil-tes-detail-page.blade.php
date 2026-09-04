<x-filament-panels::page>

@php
$app = $this->attempt->jobApplication;
$applicantName = $app->answersMap()['full_name'] ?? '—';
$correct = $this->attempt->answers->where('is_correct', true)->count();
$total = $this->attempt->answers->count();
@endphp

<x-filament::link icon="heroicon-m-arrow-left" href="{{ route('filament.rekrutmen.pages.tes-online-page') }}">
    Kembali ke Tes
</x-filament::link>

<x-filament::section>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $app->jobOpening->title }} &middot; {{ $applicantName }}</div>
            <div class="text-lg font-extrabold text-gray-950 dark:text-white mt-1">{{ $this->attempt->quiz->name }}</div>
        </div>
        <x-filament::badge :color="$this->attempt->score >= 60 ? 'success' : 'danger'" size="lg">
            {{ $correct }} dari {{ $total }} benar
        </x-filament::badge>
    </div>
    <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
        Dikumpulkan {{ $this->attempt->submitted_at?->translatedFormat('d F Y, H:i') }} WIB
        @if($this->attempt->durationLabel()) &middot; dikerjakan {{ $this->attempt->durationLabel() }} @endif
    </div>
</x-filament::section>

@foreach($this->attempt->answers as $ans)
@php $q = $ans->question; @endphp
<x-filament::section>
    <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ $loop->iteration }}. {{ $q?->question }}</div>
    <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs">
        <x-filament::badge :color="$ans->is_correct ? 'success' : 'danger'" :icon="$ans->is_correct ? 'heroicon-m-check' : 'heroicon-m-x-mark'">
            Jawaban: {{ $ans->selected_option ?? '(tidak dijawab)' }}
        </x-filament::badge>
        @if(! $ans->is_correct)
            <span class="self-center text-gray-500 dark:text-gray-400">Jawaban benar: {{ $q?->correct_option }}</span>
        @endif
    </div>
</x-filament::section>
@endforeach

</x-filament-panels::page>
