<div>
<x-page-header title="Daftar Pelatihan" />

<div style="margin-bottom:16px;">
    <livewire:pending-approvals-card :only="['lms_course_enrollment']" />
</div>

{{--
    List.js (headless, lihat resources/js/app.js) urus cari/filter kategori/filter status
    ikut/pagination — semua instan di browser, tidak ada satu pun yang roundtrip ke Livewire
    lagi begitu 14-an pelatihan published ini dimuat sekali di awal. Status "Diikuti/Belum
    Diikuti" & kategori dibaca dari data-filter-* di tiap kartu (dihitung sekali di server saat
    render, bukan query ulang tiap klik filter) lewat wireDeclarativeFilters().
--}}
<div id="training-list" data-list-table data-list-value-names="title,code" data-list-page-size="12">
    <div class="pelatihan-filter-nav">
        <button type="button" data-list-filter-key="follow" data-list-filter-value=""
            class="pelatihan-filter-btn navy is-active">
            Semua Pelatihan
        </button>
        <button type="button" data-list-filter-key="follow" data-list-filter-value="followed"
            class="pelatihan-filter-btn green">
            Diikuti
        </button>
        <button type="button" data-list-filter-key="follow" data-list-filter-value="not_followed"
            class="pelatihan-filter-btn rust">
            Belum Diikuti
        </button>
    </div>

    <div class="flex flex-col md:flex-row gap-3" style="margin-bottom:20px;">
        <div class="w-full md:w-1/2" style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;pointer-events:none;"></i>
            <input type="search" class="form-input search" placeholder="Cari pelatihan..." style="padding-left:38px;">
        </div>
        <div class="w-full md:max-w-xs" wire:ignore>
            <select data-list-filter-key="category" data-tom-select data-placeholder="Semua Kategori">
                <option value="">Semua Kategori</option>
                @foreach ($this->categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @php $courses = $this->courses; @endphp

    <div class="list grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-8" style="{{ $courses->isEmpty() ? 'display:none;' : '' }}">
        @foreach ($courses as $course)
            @php
                $accent = $this->categoryAccent($course->category?->name);
                $courseUserStatus = $this->courseUserStatus($course);
                $isPending = $courseUserStatus === 'requested';
                $followFilterValue = $courseUserStatus ? 'followed' : 'not_followed';
            @endphp
            <a href="{{ route('pelatihan.show', ['course' => $course]) }}" wire:navigate
                class="course-card accent-{{ $accent }}"
                data-filter-category="{{ $course->category_id }}"
                data-filter-follow="{{ $followFilterValue }}">
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

                    <p class="course-card-title">
                        <span class="title">{{ $course->title }}</span>
                    </p>
                    <span class="code" style="display:none;">{{ $course->code }}</span>

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

    <div class="pwa-list list-empty-state" style="padding:32px 16px;text-align:center;{{ $courses->isEmpty() ? '' : 'display:none;' }}">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pelatihan.</p>
    </div>

    <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-top:20px;">
        <button type="button" class="list-prev btn-ghost" style="padding:6px 14px;font-size:12px;">
            <i class="fa-solid fa-chevron-left" style="font-size:10px;"></i> Sebelumnya
        </button>
        <ul class="pagination" style="display:flex;flex-wrap:wrap;gap:4px;list-style:none;padding:0;margin:0;"></ul>
        <button type="button" class="list-next btn-ghost" style="padding:6px 14px;font-size:12px;">
            Selanjutnya <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
        </button>
    </div>
</div>

</div>
