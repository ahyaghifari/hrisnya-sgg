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
        @elseif ($this->isFollowed && $this->canConfirmAttendance && ! $attendanceConfirmedAt)
            <div style="display:flex;gap:8px;">
                <button type="button" wire:click="confirmAttendance" wire:loading.attr="disabled" class="course-follow-btn" style="flex:1;">Konfirmasi Kehadiran</button>
                @if ($this->izinMeetings->isNotEmpty())
                    {{-- Buka form Izin Tidak Hadir yang sama dengan link di bawah halaman. --}}
                    <button type="button" wire:click="openIzinForm" class="course-follow-btn" style="flex:1;background:var(--surface-alt,rgba(0,0,0,.05));color:var(--text-muted);border-color:var(--border);">Izin Tidak Menghadiri</button>
                @endif
            </div>
        @elseif ($this->isFollowed && $attendanceConfirmedAt)
            <button type="button" disabled class="course-follow-btn is-followed">Kehadiran terkonfirmasi · {{ $attendanceConfirmedAt }}</button>
        @elseif ($this->isFollowed)
            <button type="button" disabled class="course-follow-btn is-followed">Anda mengikuti pelatihan ini</button>
        @elseif (! $this->isCourseTrainer)
            <button type="button" wire:click="followCourse" class="course-follow-btn">Ikuti Pelatihan</button>
        @endif

        @if ($this->isFollowed && $course->requires_feedback)
            @if ($this->hasSubmittedFeedback)
                <button type="button" disabled class="course-follow-btn is-followed" style="margin-top:8px;display:block;text-align:center;">
                    <i class="fa-solid fa-circle-check"></i> Anda sudah memberikan umpan balik
                </button>
            @elseif ($course->feedback_unlocked)
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

@php $izinList = $this->izinRequests->where('status', '!=', 'cancelled'); @endphp
@if ($izinList->isNotEmpty())
    <div class="course-members-header" style="margin-top:20px;"><span class="course-members-title">Izin Tidak Hadir</span></div>
    <div class="pwa-list" style="padding:4px 12px;">
        @foreach ($izinList as $izin)
            @php
                [$izinLabel, $izinClass] = match ($izin->status) {
                    'approved' => ['Disetujui', 'is-approved'],
                    'rejected' => ['Ditolak', 'is-canceled'],
                    default => ['Menunggu', 'is-pending'],
                };
            @endphp
            <div x-data="{ open: false }" wire:key="izin-{{ $izin->id }}">
                <div class="course-member-row" style="flex-wrap:wrap;gap:8px;cursor:pointer;" @click="open = !open">
                    <span class="course-member-name">
                        {{ $izin->meeting?->title }}
                        <small style="display:block;color:var(--text-muted);font-weight:400;">{{ $izin->meeting?->start_date ? \Carbon\Carbon::parse($izin->meeting->start_date)->translatedFormat('d M Y') : '' }}</small>
                    </span>
                    <span class="course-member-status {{ $izinClass }}">{{ $izinLabel }}</span>
                    @if ($izin->canBeCancelled())
                        <button type="button" @click.stop wire:click="cancelIzin('{{ $izin->id }}')" wire:confirm="Batalkan izin ini?" style="background:none;border:0;padding:4px;font-size:12px;color:var(--text-muted);text-decoration:underline;cursor:pointer;">Batalkan</button>
                    @endif
                    <i class="fa-solid fa-chevron-down course-session-chevron" :class="{ 'is-open': open }"></i>
                </div>
                <div x-show="open" x-transition x-cloak>
                    <div style="padding:6px 4px 16px;font-size:13px;line-height:1.5;">
                        <dl style="display:grid;grid-template-columns:max-content 1fr;gap:6px 16px;margin:0;">
                            <dt style="color:var(--text-muted);">Pelatihan</dt>
                            <dd style="margin:0;color:var(--text-heading);">{{ $course->title }}</dd>

                            <dt style="color:var(--text-muted);">Pertemuan</dt>
                            <dd style="margin:0;color:var(--text-heading);">
                                {{ $izin->meeting?->title }}
                                <span style="display:block;color:var(--text-muted);font-size:12px;">
                                    @if ($izin->meeting?->start_date)
                                        {{ \Carbon\Carbon::parse($izin->meeting->start_date)->translatedFormat('l, d M Y') }}
                                    @endif
                                    @if ($izin->meeting?->start_time)
                                        &middot; {{ \Carbon\Carbon::parse($izin->meeting->start_time)->format('H:i') }}{{ $izin->meeting->end_time ? '–' . \Carbon\Carbon::parse($izin->meeting->end_time)->format('H:i') : '' }}
                                    @endif
                                </span>
                            </dd>

                            <dt style="color:var(--text-muted);">Alasan</dt>
                            <dd style="margin:0;color:var(--text-heading);white-space:pre-line;">{{ $izin->reason }}</dd>

                            <dt style="color:var(--text-muted);">Diajukan</dt>
                            <dd style="margin:0;color:var(--text-heading);">{{ $izin->created_at->translatedFormat('d M Y, H:i') }}</dd>
                        </dl>

                        @php $tl = $this->izinTimeline($izin); @endphp
                        @if (count($tl) > 1)
                            <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border-soft);">
                                <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--text-muted);">Status Persetujuan</p>
                                <ol style="list-style:none;margin:0;padding:0;display:grid;gap:12px;">
                                    @foreach ($tl as $node)
                                        @php
                                            $nodeColor = match ($node['state']) {
                                                'approved', 'done' => '#15803d',
                                                'rejected' => '#b91c1c',
                                                'cancelled' => '#475569',
                                                'current' => '#042c6c',
                                                default => '#cbd5e1',
                                            };
                                            $nodeText = match ($node['state']) {
                                                'approved', 'done' => 'Selesai',
                                                'rejected' => 'Ditolak',
                                                'cancelled' => 'Dibatalkan',
                                                'current' => 'Sedang diproses',
                                                default => 'Menunggu',
                                            };
                                        @endphp
                                        <li style="display:flex;align-items:flex-start;gap:10px;">
                                            <span style="flex-shrink:0;width:10px;height:10px;margin-top:5px;border-radius:50%;background:{{ $nodeColor }};"></span>
                                            <span style="flex:1;min-width:0;">
                                                <span style="display:block;color:var(--text-heading);font-weight:600;">{{ $node['label'] }}</span>
                                                @if ($node['actor'])
                                                    <span style="display:block;color:var(--text-muted);font-size:12px;">{{ $node['actor'] }}</span>
                                                @endif
                                            </span>
                                            <span style="flex-shrink:0;font-size:12px;color:{{ $node['date'] ? 'var(--text-muted)' : $nodeColor }};{{ $node['date'] ? '' : 'font-weight:600;' }}">
                                                {{ $node['date']?->translatedFormat('d M Y, H:i') ?? $nodeText }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@php $izinApprovals = $this->izinApprovals; @endphp
@if ($izinApprovals->isNotEmpty())
    <div class="course-members-header" style="margin-top:20px;"><span class="course-members-title">Izin Menunggu Persetujuan Anda</span></div>
    <div class="pwa-list" style="padding:4px 12px;">
        @foreach ($izinApprovals as $a)
            <div class="course-member-row" style="flex-wrap:wrap;gap:8px;">
                <span class="course-member-name">
                    {{ $a->user->name }} — {{ $a->meeting?->title }}
                    <small style="display:block;color:var(--text-muted);font-weight:400;">{{ $a->reason }}</small>
                </span>
                <button type="button" wire:click="approveIzin('{{ $a->id }}')" wire:confirm="Setujui izin ini?" style="background:none;border:0;padding:4px;font-size:12px;color:var(--text-muted);text-decoration:underline;cursor:pointer;">Setujui</button>
                <button type="button" wire:click="rejectIzin('{{ $a->id }}')" wire:confirm="Tolak izin ini?" style="background:none;border:0;padding:4px;font-size:12px;color:var(--text-muted);text-decoration:underline;cursor:pointer;">Tolak</button>
            </div>
        @endforeach
    </div>
    <input type="text" class="form-input" wire:model="izinNote" placeholder="Catatan (opsional)" style="margin-top:8px;">
@endif

@if ($this->isFollowed && in_array($courseUserStatus, ['approved', 'in_progress'], true) && $this->izinMeetings->isNotEmpty())
    <div style="margin-top:20px;text-align:center;">
        <button type="button" wire:click="$toggle('showIzin')" style="background:none;border:0;padding:6px;font-size:13px;color:var(--text-muted);text-decoration:underline;cursor:pointer;">
            Tidak bisa hadir? Ajukan izin
        </button>
    </div>
    @if ($showIzin)
        <form wire:submit="submitIzin" id="form-izin" class="form-card" style="margin-top:8px;">
            <div style="display:flex;flex-direction:column;gap:18px;">
                @if ($this->izinMeetings->count() > 1)
                    <div>
                        <label class="form-label">Pertemuan <span style="color:var(--danger-text);">*</span></label>
                        <select class="form-input" wire:model="izinMeetingId">
                            <option value="">Pilih pertemuan...</option>
                            @foreach ($this->izinMeetings as $m)
                                <option value="{{ $m->id }}">{{ $m->title }} — {{ \Carbon\Carbon::parse($m->start_date ?? $m->meeting_date)->translatedFormat('d M Y') }}</option>
                            @endforeach
                        </select>
                        @error('izinMeetingId') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                @else
                    @php $onlyMeeting = $this->izinMeetings->first(); @endphp
                    <p style="margin:0;font-size:13px;color:var(--text-muted);">
                        {{ $onlyMeeting->title }} — {{ \Carbon\Carbon::parse($onlyMeeting->start_date ?? $onlyMeeting->meeting_date)->translatedFormat('d M Y') }}
                    </p>
                @endif
                <div>
                    <label class="form-label">Alasan <span style="color:var(--danger-text);">*</span></label>
                    <textarea class="form-input" rows="3" maxlength="1000" placeholder="Masukkan alasan tidak dapat hadir..." wire:model="izinReason"></textarea>
                    @error('izinReason') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submitIzin">
                    <span wire:loading.remove wire:target="submitIzin"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan Izin</span>
                    <span wire:loading wire:target="submitIzin"><i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Diajukan...</span>
                </button>
            </div>
        </form>
    @endif
@endif

@include('livewire.lms.partials.attendance-qr-modal')

@if ($showScanner)
    <div class="modal-overlay" wire:click.self="cancelAttendanceScan" style="align-items:center;">
        <div class="modal-box"
             x-data="{
                stream: null, raf: null, err: '', zoomCap: null, z: 1,
                async start() {
                    try {
                        if (!window.jsQR) await new Promise((ok, no) => { const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.min.js'; s.onload = ok; s.onerror = no; document.head.appendChild(s); });
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                        const v = this.$refs.video; v.srcObject = this.stream; await v.play();
                        const cap = this.stream.getVideoTracks()[0].getCapabilities?.();
                        if (cap && cap.zoom) { this.zoomCap = cap.zoom; this.z = cap.zoom.min; }
                        const c = document.createElement('canvas'), x = c.getContext('2d', { willReadFrequently: true });
                        const tick = () => {
                            if (v.readyState === v.HAVE_ENOUGH_DATA) {
                                c.width = v.videoWidth; c.height = v.videoHeight; x.drawImage(v, 0, 0);
                                const r = jsQR(x.getImageData(0, 0, c.width, c.height).data, c.width, c.height);
                                if (r && r.data) { this.stop(); this.$wire.scanAttendanceQr(r.data); return; }
                            }
                            this.raf = requestAnimationFrame(tick);
                        };
                        tick();
                    } catch (e) {
                        this.err = !window.isSecureContext
                            ? 'Kamera hanya bisa dipakai lewat HTTPS. Buka halaman ini dengan alamat https://.'
                            : (e && e.name === 'NotAllowedError' ? 'Akses kamera ditolak. Izinkan kamera untuk situs ini lalu coba lagi.' : 'Kamera tidak bisa dibuka (' + (e && e.name || 'error') + ').');
                    }
                },
                setZoom(v) { this.z = v; this.stream?.getVideoTracks()[0].applyConstraints({ advanced: [{ zoom: +v }] }).catch(() => {}); },
                stop() { cancelAnimationFrame(this.raf); this.stream?.getTracks().forEach(t => t.stop()); },
                destroy() { this.stop(); }
             }"
             x-init="start()" style="text-align:center;">
            @if ($pendingScanToken)
                <i class="fa-solid fa-circle-question" style="font-size:36px;color:var(--accent, var(--brand));"></i>
                <p style="font-size:17px;font-weight:700;color:var(--text-heading);margin:10px 0 4px;">Konfirmasi {{ ucfirst($pendingScanType) }}</p>
                <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px;">{{ $pendingScanMeeting }}</p>
                <div style="display:flex;gap:8px;justify-content:center;">
                    <button type="button" wire:click="cancelAttendanceScan" class="btn-ghost" style="padding:9px 20px;font-size:13px;">Batal</button>
                    <button type="button" wire:click="confirmAttendanceScan" class="btn-primary" style="padding:9px 20px;font-size:13px;">Konfirmasi</button>
                </div>
            @else
                <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Scan QR Absensi</p>
                <div wire:ignore>
                    <video x-ref="video" playsinline muted style="width:100%;border-radius:10px;background:#000;"></video>
                    <div x-show="zoomCap" x-cloak style="display:flex;align-items:center;gap:8px;margin-top:10px;"><i class="fa-solid fa-magnifying-glass-minus" style="font-size:12px;color:var(--text-muted);"></i><input type="range" x-bind:min="zoomCap?.min" x-bind:max="zoomCap?.max" x-bind:step="zoomCap?.step || 0.1" x-bind:value="z" x-on:input="setZoom($event.target.value)" style="flex:1;"><i class="fa-solid fa-magnifying-glass-plus" style="font-size:12px;color:var(--text-muted);"></i></div>
                </div>
                <p x-show="err" x-text="err" style="color:var(--danger-text);font-size:12px;margin:10px 0 0;"></p>
                <p style="font-size:12px;color:var(--text-muted);margin:10px 0 14px;">Arahkan kamera ke QR yang diberi/ditampilkan.</p>
                <button type="button" wire:click="cancelAttendanceScan" class="btn-ghost" style="padding:8px 18px;font-size:12.5px;">Tutup</button>
            @endif
        </div>
    </div>
@endif

</div>
