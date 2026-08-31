<div class="accent-{{ $this->accent }}">
<x-page-header title="Detail Pelatihan" :back="route('pelatihan')" />

<div class="course-hero">
    <div class="course-hero-media">
        @if ($course->thumbnail)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->thumbnail) }}" alt="">
        @else
            <div class="course-hero-media-placeholder">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
        @endif

        @if ($this->isFollowed)
            <div class="course-hero-seal {{ $courseUserStatus === 'requested' ? 'is-pending' : '' }}">
                <i class="fa-solid {{ $courseUserStatus === 'requested' ? 'fa-clock' : 'fa-check' }}"></i>
                <span>{{ $courseUserStatus === 'requested' ? 'Menunggu' : 'Diikuti' }}</span>
            </div>
        @endif
    </div>

    <div class="course-hero-body">
        <span class="course-hero-eyebrow">{{ $course->category->name ?? 'Umum' }}</span>
        <span class="course-hero-code">{{ $course->code }}</span>
        <p class="course-hero-title">{{ $course->title }}</p>

        <div class="course-hero-meta">
            <span>
                <i class="fa-regular fa-calendar"></i>
                {{ $course->start_date ? \Carbon\Carbon::parse($course->start_date)->translatedFormat('d M Y') : '-' }}
                &ndash;
                {{ $course->end_date ? \Carbon\Carbon::parse($course->end_date)->translatedFormat('d M Y') : '-' }}
            </span>
            <span>
                <i class="fa-solid fa-list-check"></i>
                {{ $course->meetings->count() }} Pertemuan
            </span>
        </div>

        @if ($this->isFollowed && $courseUserStatus === 'requested')
            <button type="button" disabled class="course-follow-btn is-pending">Menunggu persetujuan admin/HC</button>
        @elseif ($this->isFollowed)
            <button type="button" disabled class="course-follow-btn is-followed">Kamu sudah mengikuti pelatihan ini</button>
        @else
            <button type="button" wire:click="followCourse" class="course-follow-btn">Ikuti Pelatihan</button>
        @endif

        @if ($this->isFollowed && $course->requires_feedback)
            @if ($course->feedback_unlocked)
                <a href="{{ route('pelatihan.feedback', ['course' => $course]) }}" wire:navigate class="course-follow-btn" style="margin-top:8px;display:block;text-align:center;">
                    <i class="fa-solid fa-star"></i> Isi Umpan Balik
                </a>
            @else
                <button type="button" disabled class="course-follow-btn is-followed" style="margin-top:8px;display:block;text-align:center;">
                    <i class="fa-solid fa-lock"></i> Umpan Balik belum dibuka
                </button>
            @endif
        @endif

        @if ($this->isCourseTrainer && $course->requires_feedback)
            <button type="button" wire:click="toggleFeedbackLock" class="course-follow-btn" style="margin-top:8px;display:block;text-align:center;{{ $course->feedback_unlocked ? 'background:#dcfce7;color:#15803d;border-color:#86efac;' : 'background:var(--surface-alt,rgba(0,0,0,.05));color:var(--text-muted);border-color:var(--border);' }}">
                <i class="fa-solid fa-{{ $course->feedback_unlocked ? 'lock-open' : 'lock' }}"></i>
                {{ $course->feedback_unlocked ? 'Umpan Balik Terbuka — Kunci' : 'Umpan Balik Terkunci — Buka' }}
            </button>
        @endif
    </div>
</div>

@if ($course->meetings->count() === 1)
    @php $meeting = $course->meetings->first(); @endphp
    {{-- 1 pertemuan saja: nomor urut/timeline tidak menambah informasi, langsung tampilkan aktivitasnya. --}}
    <div class="course-session-card">
        <div class="course-session-top">
            <span class="course-session-title">{{ $meeting->title }}</span>
            @if ($meeting->start_date)
                <span class="course-session-time">
                    {{ \Carbon\Carbon::parse($meeting->start_date)->translatedFormat('d M Y') }}
                    @if ($meeting->start_time)
                        &middot; {{ \Carbon\Carbon::parse($meeting->start_time)->format('H:i') }}{{ $meeting->end_time ? '-' . \Carbon\Carbon::parse($meeting->end_time)->format('H:i') : '' }}
                    @endif
                </span>
            @endif
        </div>
        @if ($meeting->trainer)
            <span class="course-session-trainer"><i class="fa-solid fa-chalkboard-user"></i> {{ $meeting->trainer->name }}</span>
        @endif

        @include('livewire.pelatihan.partials.session-body', ['meeting' => $meeting, 'isFollowed' => $this->isFollowed, 'isTrainer' => $this->isCourseTrainer, 'attendance' => $attendance, 'quizAttempted' => fn ($id) => $this->quizAttempted($id)])
    </div>
@else
    <div class="course-timeline">
        @foreach ($course->meetings as $meeting)
            <div class="course-session" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }">
                <div>
                    <div class="course-session-num">{{ $loop->iteration }}</div>
                    <div class="course-session-line"></div>
                </div>

                <div class="course-session-card">
                    <button type="button" @click="open = !open" class="course-session-toggle">
                        <span class="course-session-top">
                            <span class="course-session-title">{{ $meeting->title }}</span>
                            @if ($meeting->start_date)
                                <span class="course-session-time">
                                    {{ \Carbon\Carbon::parse($meeting->start_date)->translatedFormat('d M Y') }}
                                    @if ($meeting->start_time)
                                        &middot; {{ \Carbon\Carbon::parse($meeting->start_time)->format('H:i') }}{{ $meeting->end_time ? '-' . \Carbon\Carbon::parse($meeting->end_time)->format('H:i') : '' }}
                                    @endif
                                </span>
                            @endif
                        </span>
                        <i class="fa-solid fa-chevron-down course-session-chevron" :class="{ 'is-open': open }"></i>
                    </button>

                    @if ($meeting->trainer)
                        <span class="course-session-trainer"><i class="fa-solid fa-chalkboard-user"></i> {{ $meeting->trainer->name }}</span>
                    @endif

                    <div class="course-session-content" x-show="open" x-transition x-cloak>
                        @include('livewire.pelatihan.partials.session-body', ['meeting' => $meeting, 'isFollowed' => $this->isFollowed, 'isTrainer' => $this->isCourseTrainer, 'attendance' => $attendance, 'quizAttempted' => fn ($id) => $this->quizAttempted($id)])
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="course-members-header">
    <span class="course-members-title">Peserta</span>
    @if ($course->course_users_count ?? null)
        <span class="course-members-count">{{ $course->course_users_count }} orang</span>
    @endif
</div>

<div style="position:relative;margin-bottom:14px;">
    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;pointer-events:none;"></i>
    <i wire:loading wire:target="memberSearch" class="fa-solid fa-spinner fa-spin" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--brand);font-size:13px;"></i>
    <input type="search" wire:model.live.debounce.400ms="memberSearch" placeholder="Cari peserta..." class="form-input" style="padding-left:38px;padding-right:34px;">
</div>

@php $members = $this->members; @endphp

@if ($members->isEmpty())
    <div class="pwa-list" style="padding:24px 16px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">
            {{ $memberSearch ? 'Tidak ada peserta yang cocok dengan pencarian.' : 'Belum ada peserta.' }}
        </p>
    </div>
@else
    <div class="pwa-list" style="padding:4px 12px;">
        @foreach ($members as $member)
            <div class="course-member-row">
                <img src="{{ $this->memberAvatarUrl($member->user) }}" alt="" class="course-member-avatar">
                <span class="course-member-name">{{ $member->user->name ?? 'Pengguna' }}</span>
                <span class="course-member-status {{ $this->memberStatusClass($member->status) }}">{{ $this->memberStatusLabel($member->status) }}</span>
            </div>
        @endforeach
    </div>

    {{ $members->links('livewire.partials.pagination') }}
@endif

</div>
