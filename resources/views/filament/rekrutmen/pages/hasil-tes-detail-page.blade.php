<x-filament-panels::page>

@php
$app = $this->attempt->jobApplication;
$applicantName = $app->answersMap()['full_name'] ?? '—';
// Semua soal kuis (bukan hanya yang dijawab), dikelompokkan per subtest.
$sections = $this->attempt->questionsBySection();
$correct = $sections->sum('correct');
$total = $sections->sum('total');
$answered = $sections->sum('answered');
$hasSections = $sections->contains(fn ($s) => $s['name']);
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
        Skor {{ (float) $this->attempt->score }}/100 ({{ $correct }} benar &times; {{ $total > 0 ? round(100 / $total, 2) : 0 }} poin)
        &middot; {{ $answered }} dari {{ $total }} soal dijawab
        &middot; Dikumpulkan {{ $this->attempt->submitted_at?->translatedFormat('d F Y, H:i') }} WIB
        @if($this->attempt->durationLabel()) &middot; dikerjakan {{ $this->attempt->durationLabel() }} @endif
    </div>
    @if($hasSections)
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach($sections as $s)
                <x-filament::badge color="gray">
                    {{ $s['name'] ?? 'Tanpa subtest' }}: {{ $s['correct'] }}/{{ $s['total'] }} benar &middot; {{ $s['answered'] }} dijawab
                </x-filament::badge>
            @endforeach
        </div>
    @endif
</x-filament::section>

@php $no = 0; @endphp
@foreach($sections as $s)
    @if($hasSections)
        <div class="text-sm font-extrabold uppercase tracking-wide text-gray-600 dark:text-gray-300 mt-2">
            {{ $s['name'] ?? 'Tanpa subtest' }} &middot; {{ $s['correct'] }}/{{ $s['total'] }} benar
        </div>
    @endif
    @foreach($s['items'] as $item)
    @php $q = $item['question']; $ans = $item['answer']; $no++; $isCorrect = (bool) $ans?->is_correct; @endphp
    <x-filament::section>
        <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ $no }}. {{ $q->question }}</div>
        @if($img = \App\Models\Rekrutmen\QuizQuestion::imageUrl($q->image_path))
            <img src="{{ $img }}" alt="Gambar soal {{ $no }}" class="mb-3 max-h-72 rounded-lg border border-gray-200 dark:border-white/10">
        @endif
        @if(filled($q->option_images))
            @php
                $picked = array_map('trim', explode(',', (string) $ans?->selected_option));
                $rights = array_map('trim', explode(',', (string) $q->correct_option));
            @endphp
            <div class="mb-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach($q->options ?? [] as $opt)
                    @php
                        $isRight = in_array($opt, $rights, true);
                        $isPicked = in_array($opt, $picked, true);
                    @endphp
                    <div @class([
                        'rounded-lg border p-2 text-center text-xs',
                        'border-success-500 bg-success-50 dark:bg-success-500/10' => $isRight,
                        'border-danger-500 bg-danger-50 dark:bg-danger-500/10' => $isPicked && ! $isRight,
                        'border-gray-200 dark:border-white/10' => ! $isRight && ! $isPicked,
                    ])>
                        @if($optImg = \App\Models\Rekrutmen\QuizQuestion::imageUrl($q->option_images[$opt] ?? null))
                            <img src="{{ $optImg }}" alt="Pilihan {{ $opt }}" class="mx-auto mb-1 max-h-32 rounded">
                        @endif
                        <div class="font-semibold">{{ $opt }}{{ $isPicked ? ' (dipilih)' : '' }}{{ $isRight ? ' ✓' : '' }}</div>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs">
            <x-filament::badge :color="$isCorrect ? 'success' : 'danger'" :icon="$isCorrect ? 'heroicon-m-check' : 'heroicon-m-x-mark'">
                Jawaban: {{ filled($ans?->selected_option) ? $ans->selected_option : '(tidak dijawab)' }}
            </x-filament::badge>
            @if(! $isCorrect)
                <span class="self-center text-gray-500 dark:text-gray-400">Jawaban benar: {{ $q->correct_option }}</span>
            @endif
        </div>
        @if(! $isCorrect)
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach(array_map('trim', explode(',', (string) $q->correct_option)) as $rightOpt)
                    @if($rightImg = \App\Models\Rekrutmen\QuizQuestion::imageUrl($q->option_images[$rightOpt] ?? null))
                        <div class="rounded-lg border border-success-500 bg-success-50 p-2 text-center text-xs dark:bg-success-500/10">
                            <img src="{{ $rightImg }}" alt="Jawaban benar {{ $rightOpt }}" class="mx-auto mb-1 max-h-32 rounded">
                            <div class="font-semibold">{{ $rightOpt }} ✓</div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-filament::section>
    @endforeach
@endforeach

</x-filament-panels::page>
