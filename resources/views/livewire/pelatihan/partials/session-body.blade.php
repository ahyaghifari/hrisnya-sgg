@if ($isFollowed)
    @if ($attendance[$meeting->id] ?? false)
        <span class="course-attendance-pill is-present"><i class="fa-solid fa-circle-check"></i> Hadir</span>
    @else
        <span class="course-attendance-pill is-absent"><i class="fa-regular fa-circle"></i> Belum Hadir</span>
    @endif
@endif

{{-- ponytail (2026-08-06): akses kuis/materi buat karyawan login DIPARKIR dulu, publik
     (QR/token) yang jadi jalur utama sekarang - bagian ini murni tampilan/info, tidak ada
     link/aksi apapun. Backend-nya (is_unlocked, route pelatihan.quiz, QuizAttempt) tetap ada
     kalau nanti mau diaktifkan lagi, tinggal balikin <a>/wire:navigate di sini. --}}
@if ($meeting->materials->isNotEmpty())
    <div class="course-materials">
        @foreach ($meeting->materials as $material)
            <div class="course-material-row" style="cursor:default;">
                <div class="course-material-icon"><i class="fa-solid fa-file"></i></div>
                <span class="course-material-name">{{ $material->title ?? $material->file_name }}</span>
            </div>
        @endforeach
    </div>
@endif

@if ($meeting->quizzes->isNotEmpty())
    <div class="course-materials">
        @foreach ($meeting->quizzes as $meetingQuiz)
            <div class="course-material-row" style="cursor:default;{{ ($isTrainer ?? false) ? 'display:flex;align-items:center;gap:8px;' : '' }}">
                <div class="course-material-icon is-quiz"><i class="fa-solid fa-clipboard-question"></i></div>
                <span class="course-material-name">{{ $meetingQuiz->quiz->title ?? 'Kuis' }}</span>
                @if ($isTrainer ?? false)
                    <button type="button" wire:click="toggleQuizLock('{{ $meetingQuiz->id }}')" class="course-follow-btn" style="margin:0 0 0 auto;padding:4px 10px;font-size:11px;width:auto;display:inline-flex;align-items:center;gap:5px;">
                        <i class="fa-solid fa-{{ $meetingQuiz->is_unlocked ? 'lock' : 'lock-open' }}"></i>
                        {{ $meetingQuiz->is_unlocked ? 'Kunci' : 'Buka' }}
                    </button>
                @endif
            </div>
        @endforeach
    </div>
@endif
