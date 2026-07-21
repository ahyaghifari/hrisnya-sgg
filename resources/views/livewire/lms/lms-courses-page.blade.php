@use('App\Enum\LsmCourseStatus')

<div>
    <x-page-header title="Pelatihan" subtitle="Daftar pelatihan LMS" />

    <style>
        .lms-courses-table tbody tr:nth-child(even) { background: rgba(0,0,0,0.045); }
        html.dark .lms-courses-table tbody tr:nth-child(even) { background: rgba(255,255,255,0.045); }
    </style>

    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <a href="{{ route('pelatihan.kelola.create') }}" wire:navigate class="btn-primary">
            <i class="fa-solid fa-plus"></i> Tambah Pelatihan
        </a>
    </div>

    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
        <div style="min-width:200px;">
            <label class="form-label">Kategori</label>
            <select wire:model.live="categoryId" class="form-input">
                <option value="">Semua kategori</option>
                @foreach ($this->categoryOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div style="min-width:220px;flex:1;">
            <label class="form-label">Cari</label>
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari kode, judul..." style="padding-left:38px;">
            </div>
        </div>
    </div>

    @php $courses = $this->courses; @endphp

    <!-- MOBILE: card manual -->
    <div class="md:hidden">
        @if ($courses->isEmpty())
            <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pelatihan yang cocok.</p>
            </div>
        @else
            <div class="pwa-list">
                @foreach ($courses as $course)
                    @php $statusColor = LsmCourseStatus::tryFrom($course->status)?->badgeColor() ?? ['bg' => 'var(--surface-muted)', 'text' => 'var(--text-muted)']; @endphp
                    <div class="pwa-list-row">
                        <div class="pwa-tile-icon neutral"><i class="fa-solid fa-book"></i></div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $course->title }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                {{ $course->code }}
                                @if ($course->category?->name)
                                    &middot; {{ $course->category->name }}
                                @endif
                            </p>
                            <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">
                                {{ $course->start_date ? \Carbon\Carbon::parse($course->start_date)->translatedFormat('d M Y') : '-' }} &ndash; {{ $course->end_date ? \Carbon\Carbon::parse($course->end_date)->translatedFormat('d M Y') : '-' }}
                            </p>
                            <div style="margin-top:4px;display:flex;gap:6px;flex-wrap:wrap;">
                                <span class="badge" style="background:{{ $statusColor['bg'] }};color:{{ $statusColor['text'] }};">{{ LsmCourseStatus::tryFrom($course->status)?->label() ?? ucfirst($course->status ?? '-') }}</span>
                                <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ $course->course_users_count }} Peserta</span>
                            </div>
                        </div>
                        <a href="{{ route('pelatihan.kelola.show', $course) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat detail">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:16px;">
                {{ $courses->links('livewire.partials.pagination') }}
            </div>
        @endif
    </div>

    <!-- DESKTOP: tabel -->
    <div class="hidden md:block">
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $courses->isEmpty() ? 'display:none;' : '' }}">
            <table class="lms-courses-table" style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Kode</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Judul</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Kategori</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tanggal Mulai</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tanggal Selesai</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Peserta</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($courses as $course)
                        @php $statusColor = LsmCourseStatus::tryFrom($course->status)?->badgeColor() ?? ['bg' => 'var(--surface-muted)', 'text' => 'var(--text-muted)']; @endphp
                        <tr wire:key="'course-'.{{ $course->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $course->code }}</td>
                            <td style="padding:9px 14px;font-weight:600;">{{ $course->title }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $course->category?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $course->start_date ? \Carbon\Carbon::parse($course->start_date)->translatedFormat('d M Y') : '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $course->end_date ? \Carbon\Carbon::parse($course->end_date)->translatedFormat('d M Y') : '-' }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span class="badge" style="background:{{ $statusColor['bg'] }};color:{{ $statusColor['text'] }};">{{ LsmCourseStatus::tryFrom($course->status)?->label() ?? ucfirst($course->status ?? '-') }}</span>
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $course->course_users_count }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <a href="{{ route('pelatihan.kelola.show', $course) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat detail">
                                    <i class="fa-solid fa-eye"></i> Lihat
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($courses->isEmpty())
            <div style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pelatihan yang cocok.</p>
            </div>
        @endif

        <div style="margin-top:16px;">
            {{ $courses->links('livewire.partials.pagination') }}
        </div>
    </div>
</div>
