@if ($isFollowed)
    @if ($attendance[$meeting->id] ?? false)
        <span class="course-attendance-pill is-present"><i class="fa-solid fa-circle-check"></i> Hadir</span>
    @else
        <span class="course-attendance-pill is-absent"><i class="fa-regular fa-circle"></i> Belum Hadir</span>
    @endif
@endif

@if ($meeting->materials->isNotEmpty())
    <div class="course-materials">
        @foreach ($meeting->materials as $material)
            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($material->file_path) }}"
                target="_blank" rel="noopener" class="course-material-row">
                <div class="course-material-icon"><i class="fa-solid fa-file"></i></div>
                <span class="course-material-name">{{ $material->title ?? $material->file_name }}</span>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        @endforeach
    </div>
@endif

@if ($meeting->quizzes->isNotEmpty())
    <div class="course-materials">
        @foreach ($meeting->quizzes as $meetingQuiz)
            <a href="{{ route('pelatihan.quiz', ['meetingQuiz' => $meetingQuiz->id]) }}" wire:navigate class="course-material-row">
                <div class="course-material-icon is-quiz"><i class="fa-solid fa-clipboard-question"></i></div>
                <span class="course-material-name">{{ $meetingQuiz->quiz->title ?? 'Kuis' }}</span>
                @if ($quizAttempted($meetingQuiz->id))
                    <span class="badge badge-approved">Selesai</span>
                @endif
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        @endforeach
    </div>
@endif
