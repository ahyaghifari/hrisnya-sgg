<div>
<x-page-header title="Daftar Pelatihan" />

<div class="pelatihan-filter-nav">
    <button type="button" wire:click="$set('followStatus', 'all')"
        class="pelatihan-filter-btn navy {{ $followStatus === 'all' ? 'is-active' : '' }}">
        Semua Pelatihan
    </button>
    <button type="button" wire:click="$set('followStatus', 'followed')"
        class="pelatihan-filter-btn green {{ $followStatus === 'followed' ? 'is-active' : '' }}">
        Diikuti
    </button>
    <button type="button" wire:click="$set('followStatus', 'not_followed')"
        class="pelatihan-filter-btn rust {{ $followStatus === 'not_followed' ? 'is-active' : '' }}">
        Belum Diikuti
    </button>
</div>

<div class="flex flex-col md:flex-row gap-3" style="margin-bottom:20px;">
    <div class="w-full md:w-1/2" style="position:relative;">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;pointer-events:none;"></i>
        <i wire:loading wire:target="search" class="fa-solid fa-spinner fa-spin" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--brand);font-size:13px;"></i>
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari pelatihan..." class="form-input" style="padding-left:38px;padding-right:34px;">
    </div>
    <div class="w-full md:max-w-xs" wire:ignore>
        <select wire:model.live="categoryId" data-tom-select data-placeholder="Semua Kategori">
            <option value="">Semua Kategori</option>
            @foreach ($this->categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
</div>

@php $courses = $this->courses; @endphp

@if ($courses->isEmpty())
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pelatihan.</p>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-8">
        @foreach ($courses as $course)
            @php
                $accent = $this->categoryAccent($course->category?->name);
                $courseUserStatus = $this->courseUserStatus($course);
                $isPending = $courseUserStatus === 'requested';
            @endphp
            <a href="{{ route('pelatihan.show', ['course' => $course]) }}" wire:navigate class="course-card accent-{{ $accent }}">
                <div class="course-card-media">
                    @if ($course->thumbnail)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->thumbnail) }}" alt="">
                    @else
                        <div class="course-card-media-placeholder">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                    @endif

                    @if ($courseUserStatus)
                        <div class="course-card-seal {{ $isPending ? 'is-pending' : '' }}">
                            <i class="fa-solid {{ $isPending ? 'fa-clock' : 'fa-check' }}"></i>
                            <span>{{ $isPending ? 'Menunggu' : 'Diikuti' }}</span>
                        </div>
                    @endif
                </div>

                <div class="course-card-body">
                    <div class="course-card-top-row">
                        <span class="course-card-eyebrow">{{ $course->category->name ?? 'Umum' }}</span>
                        <span class="course-card-code">{{ $course->code }}</span>
                    </div>

                    <p class="course-card-title">{{ $course->title }}</p>

                    <div class="course-card-meta">
                        <i class="fa-regular fa-calendar"></i>
                        <span>
                            {{ $course->start_date ? \Carbon\Carbon::parse($course->start_date)->translatedFormat('d M') : '-' }}
                            &ndash;
                            {{ $course->end_date ? \Carbon\Carbon::parse($course->end_date)->translatedFormat('d M Y') : '-' }}
                        </span>
                        @if ($course->meetings_count)
                            <span class="course-card-meta-dot">&bull;</span>
                            <span>{{ $course->meetings_count }} pertemuan</span>
                        @endif
                    </div>

                    <div class="course-card-roster">
                        @foreach ($this->memberAvatars($course) as $avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="">
                        @endforeach
                        @if ($course->course_users_count > 0)
                            <span class="course-card-roster-count">+{{ $course->course_users_count }} peserta</span>
                        @endif
                    </div>

                    <span class="course-card-cta">
                        Lihat Detail <i class="fa-solid fa-arrow-right"></i>
                    </span>
                </div>
            </a>
        @endforeach
    </div>

    {{ $courses->links('livewire.pelatihan.partials.pagination') }}
@endif

</div>
