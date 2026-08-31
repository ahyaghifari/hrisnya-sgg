@if ($isFollowed)
    @if ($attendance[$meeting->id] ?? false)
        <span class="course-attendance-pill is-present"><i class="fa-solid fa-circle-check"></i> Hadir</span>
    @else
        <span class="course-attendance-pill is-absent"><i class="fa-regular fa-circle"></i> Belum Hadir</span>
    @endif
@endif

{{-- Materi: info saja (belum ada jalur unduh dari portal karyawan). --}}
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

{{-- Kuis: link dikembalikan 2026-08-29 (akses publik/QR dipensiunkan, pengerjaan via HRIS).
     Peserta bisa klik kalau sudah diikuti & kuis dibuka trainer (is_unlocked); trainer dapat
     toggle Buka/Kunci di sini. --}}
@if ($meeting->quizzes->isNotEmpty())
    <div class="course-materials">
        @foreach ($meeting->quizzes as $meetingQuiz)
            @php
                $attempted = ($quizAttempted ?? null) ? $quizAttempted($meetingQuiz->id) : false;
                $participantCanOpen = ($isFollowed ?? false) && $meetingQuiz->is_unlocked;
            @endphp

            @if (! ($isTrainer ?? false) && $participantCanOpen && ! $attempted)
                <a href="{{ route('pelatihan.quiz', ['meetingQuiz' => $meetingQuiz->id]) }}" wire:navigate class="course-material-row" style="text-decoration:none;">
                    <div class="course-material-icon is-quiz"><i class="fa-solid fa-clipboard-question"></i></div>
                    <span class="course-material-name">{{ $meetingQuiz->quiz->title ?? 'Kuis' }}</span>
                    <span class="course-follow-btn" style="margin:0 0 0 auto;padding:4px 10px;font-size:11px;width:auto;">Kerjakan</span>
                </a>
            @else
                <div class="course-material-row" style="cursor:default;{{ ($isTrainer ?? false) ? 'display:flex;align-items:center;gap:8px;' : '' }}">
                    <div class="course-material-icon is-quiz"><i class="fa-solid fa-clipboard-question"></i></div>
                    <span class="course-material-name">{{ $meetingQuiz->quiz->title ?? 'Kuis' }}</span>

                    @if ($isTrainer ?? false)
                        <button type="button" wire:click="toggleQuizLock('{{ $meetingQuiz->id }}')" class="course-follow-btn" style="margin:0 0 0 auto;padding:4px 10px;font-size:11px;width:auto;display:inline-flex;align-items:center;gap:5px;{{ $meetingQuiz->is_unlocked ? 'background:#dcfce7;color:#15803d;border-color:#86efac;' : 'background:var(--surface-alt,rgba(0,0,0,.05));color:var(--text-muted);border-color:var(--border);' }}">
                            <i class="fa-solid fa-{{ $meetingQuiz->is_unlocked ? 'lock-open' : 'lock' }}"></i>
                            {{ $meetingQuiz->is_unlocked ? 'Terbuka — Kunci' : 'Terkunci — Buka' }}
                        </button>
                    @elseif ($attempted)
                        <span class="course-attendance-pill is-present" style="margin-left:auto;"><i class="fa-solid fa-circle-check"></i> Sudah dikerjakan</span>
                    @elseif ($isFollowed ?? false)
                        <span class="course-attendance-pill is-absent" style="margin-left:auto;"><i class="fa-solid fa-lock"></i> Belum dibuka</span>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
@endif
