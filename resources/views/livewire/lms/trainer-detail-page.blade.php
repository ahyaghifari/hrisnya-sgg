<div>
    <style>
        .lms-course-tab { padding: 10px 4px; margin-right: 22px; font-size: 13px; font-weight: 600; color: var(--text-faint); background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-family: inherit; }
        .lms-course-tab.is-active { color: #042c6c; border-bottom-color: #042c6c; }
        html.dark .lms-course-tab.is-active { color: #6ea8e0; border-bottom-color: #6ea8e0; }
    </style>

    {{-- :title/:subtitle (bukan title="{{ }}") - dobel-escape kalau namanya pakai "&" dst, lihat
    catatan sama di schedule-detail-page.blade.php. --}}
    <x-page-header :title="$trainer->name" :subtitle="ucfirst($trainer->type)" :back="route('pelatihan.pemateri')" />

    <div style="border-bottom:1px solid var(--border-soft);margin-bottom:18px;">
        <button type="button" wire:click="setTab('profil')" class="lms-course-tab {{ $tab === 'profil' ? 'is-active' : '' }}">Profil</button>
        <button type="button" wire:click="setTab('pelatihan')" class="lms-course-tab {{ $tab === 'pelatihan' ? 'is-active' : '' }}">Pelatihan</button>
        <button type="button" wire:click="setTab('review')" class="lms-course-tab {{ $tab === 'review' ? 'is-active' : '' }}">Review</button>
    </div>

    {{-- Tab Profil --}}
    @if ($tab === 'profil')
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Nama</span>
            <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $trainer->name }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Tipe</span>
            <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ ucfirst($trainer->type) }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Status</span>
            <span class="badge {{ $trainer->is_active ? 'badge-approved' : 'badge-rejected' }}">{{ $trainer->is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        @if ($trainer->user)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
                <span style="font-size:12.5px;color:var(--text-muted);">Karyawan</span>
                <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $trainer->user->name }}</span>
            </div>
        @endif
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Keahlian</span>
            <span style="font-size:13.5px;color:var(--text-body);">{{ $trainer->expertise ?: '-' }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Perusahaan Asal</span>
            <span style="font-size:13.5px;color:var(--text-body);">{{ $trainer->company ?: '-' }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Email</span>
            <span style="font-size:13.5px;color:var(--text-body);">{{ $trainer->email ?: '-' }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Telepon</span>
            <span style="font-size:13.5px;color:var(--text-body);">{{ $trainer->phone ?: '-' }}</span>
        </div>
        <div style="padding-top:12px;">
            <span style="font-size:12.5px;color:var(--text-muted);display:block;margin-bottom:6px;">Alamat</span>
            <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $trainer->address ?: '-' }}</p>
        </div>
    </div>

    {{-- Tab Pelatihan --}}
    @elseif ($tab === 'pelatihan')
    <div class="pwa-list">
        @forelse ($this->courses as $course)
            <a href="{{ route('pelatihan.kelola.show', $course) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">{{ $course->title }}</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">
                        {{ $course->code }}
                        @if ($course->start_date)
                            &middot; {{ \Illuminate\Support\Carbon::parse($course->start_date)->translatedFormat('d M Y') }}
                        @endif
                    </span>
                </div>
                <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ ucfirst($course->status ?? '-') }}</span>
            </a>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada pelatihan</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Pemateri ini belum pernah mengisi pertemuan di pelatihan mana pun.</span>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Tab Review --}}
    @elseif ($tab === 'review')
    <div style="display:flex;flex-direction:column;gap:10px;">
        @forelse ($this->reviews as $review)
            <div style="background:var(--surface);border-radius:14px;box-shadow:var(--shadow-card);padding:14px 16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:6px;">
                    <span style="font-size:13px;font-weight:700;color:var(--text-heading);">{{ $review->course?->title ?? '-' }}</span>
                    @if ($review->rating !== null)
                        <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">
                            <i class="fa-solid fa-star" style="color:#f59e0b;"></i> {{ $review->rating }}
                        </span>
                    @endif
                </div>
                <p style="font-size:12.5px;color:var(--text-body);margin:0 0 6px;white-space:pre-line;">{{ $review->feedback ?: '-' }}</p>
                <span style="font-size:11px;color:var(--text-muted);">{{ $review->respondent?->name ?? 'Anonim' }} &middot; {{ $review->created_at->translatedFormat('d M Y') }}</span>
            </div>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada review</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Belum ada feedback peserta dari pelatihan yang diisi pemateri ini.</span>
                </div>
            </div>
        @endforelse
    </div>
    @endif
</div>
