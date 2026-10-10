<div class="accent-{{ $this->accent }}"
    x-data
    x-on:quiz-submitted.window="
        window.scrollTo({ top: 0, behavior: 'smooth' });
        const here = location.pathname;
        setTimeout(() => { if (location.pathname === here) Livewire.navigate($event.detail.url); }, 5000);
    ">
<x-page-header title="Kuis" :back="route('pelatihan.show', ['course' => $meetingQuiz->meeting->course])" />

<div class="quiz-header">
    <p class="quiz-header-title">{{ $quizTitle }} &ndash; {{ $meetingQuiz->meeting->course->title }}</p>
    <div class="quiz-header-meta">
        @if ($trainerName)
            <span><i class="fa-solid fa-chalkboard-user"></i> {{ $trainerName }}</span>
        @endif
        @if ($meetingQuiz->meeting->start_date)
            <span>
                <i class="fa-regular fa-calendar"></i>
                {{ \Carbon\Carbon::parse($meetingQuiz->meeting->start_date)->translatedFormat('d M Y') }}
                @if ($meetingQuiz->meeting->start_time)
                    &middot; {{ \Carbon\Carbon::parse($meetingQuiz->meeting->start_time)->format('H:i') }}{{ $meetingQuiz->meeting->end_time ? '-' . \Carbon\Carbon::parse($meetingQuiz->meeting->end_time)->format('H:i') : '' }}
                @endif
            </span>
        @endif
        @if ($duration)
            <span><i class="fa-regular fa-clock"></i> {{ $duration }} menit</span>
        @endif
    </div>
</div>

@if ($locked)
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <i class="fa-solid fa-lock" style="font-size:22px;color:var(--text-faint);margin-bottom:10px;"></i>
        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Kuis belum dibuka</p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Admin/pemateri belum membuka kuis ini untuk dikerjakan.</p>
    </div>
@elseif ($blocked)
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <i class="fa-solid fa-lock" style="font-size:22px;color:var(--text-faint);margin-bottom:10px;"></i>
        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Belum bisa mengerjakan kuis ini</p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Kamu belum disetujui sebagai peserta pelatihan ini oleh admin/HC.</p>
    </div>
@elseif ($result)
    <div class="quiz-result-card">
        <p class="quiz-result-status">Kuis sudah kamu kerjakan</p>

        <div class="quiz-result-cols">
            <div class="quiz-result-col is-accent-col">
                <p class="quiz-result-num" style="color:var(--accent);">{{ (int) $result->total_score }}/{{ (int) $result->max_score }}</p>
                <p class="quiz-result-label">Skor</p>
            </div>
            <div class="quiz-result-col is-correct-col">
                <p class="quiz-result-num is-correct-num">{{ $result->correct_count }}</p>
                <p class="quiz-result-label">Benar</p>
            </div>
            <div class="quiz-result-col is-wrong-col">
                <p class="quiz-result-num is-wrong-num">{{ $result->wrong_count }}</p>
                <p class="quiz-result-label">Salah</p>
            </div>
        </div>
    </div>
@elseif (empty($questions))
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada soal pilihan ganda di kuis ini.</p>
    </div>
@else
    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
        @foreach ($questions as $question)
            <div class="course-session-card">
                <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin-bottom:12px;">
                    {{ $question['no'] }}. {{ trim(html_entity_decode(strip_tags($question['question_text']))) }}
                </p>

                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach ($question['options'] as $option)
                        <button type="button"
                            wire:click="selectAnswer('{{ $question['id'] }}', '{{ $option['id'] }}')"
                            class="quiz-option {{ ($answers[$question['id']] ?? null) === $option['id'] ? 'is-selected' : '' }}">
                            {{ $option['text'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" wire:click="submit" class="pwa-action-btn navy" style="width:100%;padding:14px;border:none;color:#fff;font-weight:700;font-size:14px;">Kirim Jawaban</button>
@endif

</div>
