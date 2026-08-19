<div id="push-notif-banner" style="display:none;background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:14px;padding:12px 14px;margin-bottom:16px;align-items:center;gap:12px;">
    <i class="fa-solid fa-bell" style="color:var(--brand);"></i>
    <p style="flex:1;font-size:12.5px;color:var(--text-body);margin:0;">Aktifkan notifikasi agar tahu saat pengajuan Anda disetujui/ditolak.</p>
    <button type="button" onclick="pwaSubscribeToPush()" style="background:#042c6c;color:#fff;border:none;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">Aktifkan</button>
    <button type="button" onclick="pwaDismissPushBanner()" style="background:none;border:none;color:var(--text-faint);font-size:12px;cursor:pointer;">Nanti</button>
</div>

<div class="bento pwa-bento {{ ! $this->canPengajuanAbsensi ? 'no-pengajuan' : '' }} {{ ! $this->canPelatihanSaya ? 'no-pelatihan' : '' }}">

    <!-- WELCOME CARD (server-rendered, bukan domain attendance) -->
    @php
        $profilePhotoPath = auth()->user()->profile?->profile_picture[0] ?? null;
        $profilePhotoUrl = $profilePhotoPath && is_string($profilePhotoPath) ? \Illuminate\Support\Facades\Storage::url($profilePhotoPath) : null;
    @endphp
    <div class="welcome-card bento-welcome">
        <div class="w-deco-1"></div><div class="w-deco-2"></div><div class="w-deco-4"></div>
        <div style="position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:14px;">
                {{-- Foto profil cuma ditampilkan kalau memang sudah upload - tidak ada fallback
                inisial/placeholder di sini (beda dari x-avatar), biar welcome-card tidak boros
                ruang untuk user yang belum punya foto. --}}
                @if ($profilePhotoUrl)
                    <img src="{{ $profilePhotoUrl }}" alt="{{ auth()->user()->name }}" class="welcome-avatar">
                @endif
                <div>
                    <p class="welcome-greeting">Selamat datang kembali 👋</p>
                    <h2 class="welcome-name">{{ auth()->user()->name }}</h2>
                    <p class="welcome-meta">
                        {{ auth()->user()->profile?->jobPosition?->name ?? 'Karyawan' }}
                        &nbsp;·&nbsp; NIP {{ auth()->user()->nip }}
                        &nbsp;·&nbsp; {{ auth()->user()->company?->name ?? '-' }}
                    </p>
                </div>
            </div>

            <div class="welcome-actions">
                <a href="{{ route('profil-saya') }}" wire:navigate class="data-diri-btn gold">
                    <i class="fa-solid fa-id-card"></i> Profil Saya
                </a>
                @if (auth()->user()->hasRole(['Admin', 'Super Admin']))
                    <a href="/admin" class="data-diri-btn">
                        <i class="fa-solid fa-gauge"></i> Admin Panel
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if ($this->canPengajuanAbsensi)
        <div class="bento-status-pengajuan">
            <livewire:my-attendance-status-card />
        </div>
    @endif

    <!-- PRESENSI: status hari ini (jadwal + libur/cuti/izin + jam presensi asli) -->
    <x-today-attendance-card :status="$this->todayAttendanceStatus" id="absen-section" class="bento-absensi" />

    {{-- Cuti Tahunan + Jumlah Izin + Jumlah Lembur, berdampingan dengan presensi di layar lebar.
    Dua variasi ukuran (LeaveBalanceCard/AttendanceStatCards menghitung style-nya di server lewat
    prop `compact`, jadi tidak bisa satu markup untuk semua breakpoint) — visibility diatur lewat
    class .bento-side-stats(-compact) di app.css, bukan Tailwind md:hidden (lihat komentar di sana).
    Sama seperti card Pengajuan Absensi Saya, ikut disembunyikan tanpa permission itu - isinya
    murni statistik cuti/izin/lembur (domain Pengajuan Absensi), bukan hal terpisah. --}}
    @if ($this->canPengajuanAbsensi)
        <div class="bento-side-stats-compact">
            <livewire:leave-balance-card
                type-keyword="tahunan"
                label="Sisa Cuti Tahunan"
                icon="fa-sun"
                color="var(--brand)"
                icon-bg="rgba(4,44,108,0.1)"
                grid-class=""
                :compact="true"
                :key="'leave-balance-tahunan-compact'"
            />
            <livewire:attendance-stat-cards :compact="true" :key="'attendance-stat-cards-compact'" />
        </div>
        <div class="bento-side-stats">
            <livewire:leave-balance-card
                type-keyword="tahunan"
                label="Sisa Cuti Tahunan"
                icon="fa-sun"
                color="var(--brand)"
                icon-bg="rgba(4,44,108,0.1)"
                grid-class=""
                :key="'leave-balance-tahunan'"
            />
            <livewire:attendance-stat-cards :key="'attendance-stat-cards'" />
        </div>
    @endif

    {{-- MyKanban: task terkini dari kanban pribadi maupun board (bukan lagi placeholder statis).
    Sekelompok dengan welcome/presensi/saldo di grid .bento yang sama (bukan section terpisah di
    luar grid), supaya breakpoint-nya konsisten: 1 kolom di mobile, 2 kolom berdampingan di desktop.
    Tugas Saya disembunyikan tanpa permission TUGAS_SAYA, sama seperti Pengajuan Absensi/Pelatihan
    Saya di atas - kalau keduanya (Tugas & Pelatihan) tidak ada permission, seluruh section ini
    tidak dirender sama sekali, bukan dibiarkan jadi kotak kosong. --}}
    @if ($this->canTugasSaya || $this->canPelatihanSaya)
    <div class="bento-task-kursus {{ ! $this->canPelatihanSaya ? 'no-pelatihan' : '' }} {{ ! $this->canTugasSaya ? 'no-tugas' : '' }}">
        @if ($this->canTugasSaya)
        <div class="pwa-list">
            <div class="pwa-card-header pwa-card-header-navy">
                <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
                <div class="pwa-card-header-content">
                    <span class="pwa-section-title"><i class="fa-solid fa-table-columns" style="font-size:12px;margin-right:4px;"></i> Tugas Saya</span>
                    <a href="{{ route('tugas') }}" wire:navigate class="pwa-section-link">Semua <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i></a>
                </div>
            </div>
            @php
                $statusColor = fn (string $s) => \App\Enum\Task\TaskStatus::tryFrom($s)?->getColor();
                $statusColorHex = fn (string $s) => ['gray' => '#64748b', 'warning' => '#d97706', 'info' => '#1a56c4', 'success' => '#047C54', 'danger' => '#be123c'][$statusColor($s)] ?? '#64748b';
                $statusLabel = fn (string $s) => \App\Enum\Task\TaskStatus::tryFrom($s)?->getLabel() ?? $s;
            @endphp
            @forelse ($this->recentTasks as $task)
                @php
                    // Task::due_date sengaja tidak di-cast ke date (lihat komentar di model) —
                    // Carbon::parse() manual di sini, bukan panggil method Carbon langsung di
                    // kolom mentah.
                    $dueDate = $task->due_date ? \Carbon\Carbon::parse($task->due_date) : null;
                    $isOverdue = $dueDate && $dueDate->lt(now()->startOfDay());
                    $accent = $isOverdue ? '#be123c' : $statusColorHex($task->status);
                @endphp
                <a href="{{ route('tugas') }}" wire:navigate class="pwa-list-row" style="text-decoration:none;border-left:4px solid {{ $accent }};padding-left:12px;">
                    <div style="flex:1;min-width:0;">
                        <p class="pwa-row-title">{{ \Illuminate\Support\Str::limit($task->requirement, 50) }}</p>
                        <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:700;color:{{ $accent }};margin-top:4px;">
                            {{ $isOverdue ? 'Overdue · ' . $dueDate->translatedFormat('j M') : $statusLabel($task->status) }}
                        </span>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @empty
                <div class="pwa-list-row" style="opacity:0.7;border-left:4px solid var(--border);padding-left:12px;">
                    <div style="flex:1;min-width:0;">
                        <p class="pwa-row-title">Belum ada tugas</p>
                        <span style="font-size:11.5px;color:var(--text-muted);">Buat tugas baru lewat menu Tugas</span>
                    </div>
                </div>
            @endforelse
        </div>
        @endif

        @if ($this->canPelatihanSaya)
        <div class="pwa-list" style="padding-bottom:16px;">
            <div class="pwa-card-header pwa-card-header-violet" style="margin-bottom:14px;">
                <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
                <div class="pwa-card-header-content">
                    <span class="pwa-section-title"><i class="fa-solid fa-graduation-cap" style="font-size:12px;margin-right:4px;"></i> Pelatihan Saya</span>
                    <a href="{{ route('pelatihan') }}" wire:navigate class="pwa-section-link">Semua <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i></a>
                </div>
            </div>
            @php
                $trainingStatusLabel = fn (string $s) => ['requested' => 'Menunggu', 'approved' => 'Disetujui', 'in_progress' => 'Berjalan'][$s] ?? $s;
                $trainingBadgeClass = fn (string $s) => ['requested' => 'badge-pending', 'approved' => 'badge-approved', 'in_progress' => 'badge-pending'][$s] ?? 'badge-cancelled';
            @endphp
            @if ($this->myTrainings->isEmpty())
                <div class="pwa-list-row" style="opacity:0.7;padding-left:16px;padding-right:16px;">
                    <div style="flex:1;min-width:0;">
                        <p class="pwa-row-title">Belum ada pelatihan yang diikuti</p>
                        <span style="font-size:11.5px;color:var(--text-muted);">Cari pelatihan lewat menu Pelatihan</span>
                    </div>
                </div>
            @else
                <div class="pwa-scroll-row" style="padding-left:16px;padding-right:16px;">
                    @foreach ($this->myTrainings as $courseUser)
                        @php $accent = $this->categoryAccent($courseUser->course->category?->name); @endphp
                        <a href="{{ route('pelatihan.show', ['course' => $courseUser->course_id]) }}" wire:navigate class="course-card-mini accent-{{ $accent }}">
                            <div class="course-card-mini-media">
                                @if ($courseUser->course->thumbnail)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($courseUser->course->thumbnail) }}" alt="">
                                @else
                                    <i class="fa-solid fa-graduation-cap"></i>
                                @endif
                            </div>
                            <div class="course-card-mini-body">
                                <span class="course-card-mini-eyebrow">{{ $courseUser->course->category?->name ?? 'Umum' }}</span>
                                <p class="course-card-mini-title">{{ $courseUser->course->title }}</p>
                                @if ($courseUser->status === 'in_progress')
                                    <div class="progress-track"><div class="progress-fill" style="width:{{ $courseUser->progress }}%;background:#047C54;"></div></div>
                                @endif
                                <span class="badge {{ $trainingBadgeClass($courseUser->status) }}">{{ $trainingStatusLabel($courseUser->status) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        @endif
    </div>
    @endif

    @if ($this->canJadwalShiftSaya)
    <div class="pwa-list" style="grid-column:1/-1;padding-bottom:16px;">
        <div class="pwa-card-header pwa-card-header-sky" style="margin-bottom:14px;">
            <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
            <div class="pwa-card-header-content">
                <span class="pwa-section-title"><i class="fa-solid fa-calendar-days" style="font-size:12px;margin-right:4px;"></i> Jadwal Shift Saya</span>
                @if ($this->myScheduleId)
                    <a href="{{ route('presensi.jadwal.show', ['schedule' => $this->myScheduleId, 'tab' => 'penjadwalan']) }}" wire:navigate class="pwa-section-link">Detail Jadwal <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i></a>
                @endif
            </div>
        </div>
        <div class="jadwal-shift-calendar-wrap" style="padding:0 16px;">
            <div class="jadwal-shift-calendar-col">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <button type="button" wire:click="calendarPrevMonth" class="btn-ghost" style="padding:4px 9px;font-size:11px;"><i class="fa-solid fa-chevron-left"></i></button>
                    <span style="font-size:12px;font-weight:700;color:var(--text-heading);">{{ $this->calendarMonthLabel }}</span>
                    <button type="button" wire:click="calendarNextMonth" class="btn-ghost" style="padding:4px 9px;font-size:11px;"><i class="fa-solid fa-chevron-right"></i></button>
                </div>

                <div class="jadwal-shift-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;font-size:9.5px;font-weight:700;color:var(--text-faint);text-align:center;margin-bottom:4px;">
                    <span>Min</span><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span>
                </div>
                <div class="jadwal-shift-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;">
                    @foreach ($this->calendarWeeks as $week)
                        @foreach ($week as $day)
                            @php
                                $schedule = $day['schedule'];
                                $state = $schedule['state'] ?? null;

                                // Sel 'scheduled' pakai warna SOLID persis dari config shift type (bukan
                                // color-mix tint lagi) - instruksi user 2026-08-15, kartu identitas warna
                                // fixed begini exempt dari aturan var(--token) DESIGN.md (sama pola
                                // .badge-*/.pwa-tile-icon, warnanya sendiri yang jadi identitas, bukan ikut
                                // tema). Teks putih solid biar kontras di atas warna jenuh apapun.
                                $cellColor = match ($state) {
                                    'holiday' => 'var(--warn-soft-text)',
                                    'off' => 'var(--danger-soft-text)',
                                    'scheduled' => $schedule['color'] ? '#fff' : 'var(--info-soft-text)',
                                    default => 'var(--text-faint)',
                                };
                                $cellBg = match ($state) {
                                    'holiday' => 'var(--warn-soft-bg)',
                                    'off' => 'var(--danger-soft-bg)',
                                    'scheduled' => $schedule['color'] ? $schedule['color'] : 'var(--info-soft-bg)',
                                    default => 'transparent',
                                };
                            @endphp
                            <div
                                @if ($day['is_current_month'] && $schedule && $schedule['label']) title="{{ $schedule['label'] }}" @endif
                                style="aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:7px;font-size:10.5px;gap:1px;
                                    {{ $day['is_current_month'] ? "background:{$cellBg};color:{$cellColor};font-weight:700;" : 'color:var(--text-faint);' }}
                                    {{ $day['is_today'] ? 'box-shadow:inset 0 0 0 1.5px var(--brand);' : '' }}">
                                {{ $day['date']->day }}
                                @if ($day['is_current_month'] && $state === 'scheduled' && $schedule['code'])
                                    <span style="font-size:12px;font-weight:800;letter-spacing:0.2px;line-height:1;">{{ $schedule['code'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="jadwal-shift-side-col">
                <div style="display:flex;flex-direction:column;gap:5px;">
                    @foreach ($this->shiftLegend as $legend)
                        <span class="jadwal-shift-legend-item" style="font-size:11px;color:var(--text-body);display:inline-flex;align-items:center;gap:6px;">
                            <span style="width:8px;height:8px;border-radius:50%;background:{{ $legend['color'] ?? 'var(--info-soft-text)' }};display:inline-block;flex-shrink:0;"></span>
                            <strong style="color:var(--text-heading);">{{ $legend['code'] }}</strong> &ndash; {{ $legend['label'] }}
                            @if ($legend['detail'])
                                <span style="color:var(--text-muted);">({{ $legend['detail'] }})</span>
                            @endif
                        </span>
                    @endforeach
                    <span class="jadwal-shift-legend-item" style="font-size:11px;color:var(--text-body);display:inline-flex;align-items:center;gap:6px;">
                        <span style="width:8px;height:8px;border-radius:50%;background:var(--danger-soft-text);display:inline-block;flex-shrink:0;"></span>Libur
                    </span>
                    <span class="jadwal-shift-legend-item" style="font-size:11px;color:var(--text-body);display:inline-flex;align-items:center;gap:6px;">
                        <span style="width:8px;height:8px;border-radius:50%;background:var(--warn-soft-text);display:inline-block;flex-shrink:0;"></span>Hari Libur Nasional
                    </span>
                </div>

                <livewire:presensi.shift-swap-request-modal :key="'shift-swap-modal'" />
        </div>
    </div>
    @endif

    @if ($this->canJadwalShiftTim)
        <livewire:presensi.team-shift-schedule-card :key="'team-shift-schedule'" />
    @endif

    <div class="pwa-list" style="grid-column:1/-1;">
        <a href="{{ route('organization-structure') }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
            <i class="fa-solid fa-sitemap" style="width:16px;text-align:center;color:var(--brand);"></i>
            <span class="pwa-row-title" style="flex:1;">Struktur Organisasi</span>
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    </div>

</div>

@push('scripts')

<script>
function pwaDismissPushBanner() {
    // Sengaja TIDAK disimpan ke localStorage (dulu disupres 7 hari) - banner harus tetap
    // muncul lagi di kunjungan/reload berikutnya selama notifikasi belum benar-benar aktif.
    document.getElementById('push-notif-banner').style.display = 'none';
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

async function pwaSubscribeToPush() {
    const vapidKey = document.querySelector('meta[name="vapid-public-key"]').content;
    const registration = await navigator.serviceWorker.ready;
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        pwaDismissPushBanner();
        return;
    }

    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidKey),
    });

    await fetch('{{ route('push.subscribe') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(subscription.toJSON()),
    });

    document.getElementById('push-notif-banner').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', async () => {
    const banner = document.getElementById('push-notif-banner');

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }
    if (Notification.permission !== 'default') {
        return;
    }

    const registration = await navigator.serviceWorker.ready;
    const existing = await registration.pushManager.getSubscription();
    if (!existing) {
        banner.style.display = 'flex';
    }
});
</script>
@endpush
