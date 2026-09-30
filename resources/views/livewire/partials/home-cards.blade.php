{{-- Ajakan memasang aplikasi (PWA) ke perangkat - ditaruh SEBELUM banner notifikasi karena push di iOS baru
jalan setelah aplikasi dipasang ke layar utama. wire:ignore: display-nya diatur JS, jangan di-morph Livewire. --}}
{{-- Warna EMAS (palet sama tombol emas "Profil Saya"): blok warna tetap, jadi sama di light & dark mode. Style inline
di sini (tanpa build CSS). display diatur JS (inline none -> flex), jangan diset di class. --}}
<style>
    .pwa-install-banner { background: linear-gradient(135deg, #fde68a 0%, #f5c518 55%, #d4a017 100%); border: 1px solid rgba(146, 64, 14, 0.35); border-radius: 14px; padding: 12px 14px; margin-bottom: 16px; align-items: center; gap: 12px; box-shadow: 0 6px 16px rgba(212, 160, 23, 0.32); color: #4a3403; position: relative; overflow: hidden; }
    .pwa-install-banner::after { content: ''; position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(120deg, transparent 0%, rgba(255,255,255,0.55) 50%, transparent 100%); transform: skewX(-20deg); animation: gold-shimmer 6s ease-in-out infinite; pointer-events: none; }
    .pwa-install-banner-icon { position: relative; width: 36px; height: 36px; flex-shrink: 0; border-radius: 11px; background: rgba(255, 255, 255, 0.55); color: #6b4a05; display: flex; align-items: center; justify-content: center; font-size: 16px; }
    .pwa-install-banner-text { position: relative; flex: 1; min-width: 0; margin: 0; font-size: 12.5px; font-weight: 600; line-height: 1.4; color: #4a3403; }
    .pwa-install-banner-btn { position: relative; background: #4a3403; color: #fde68a; border: none; border-radius: 9px; padding: 7px 14px; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; font-family: inherit; transition: filter 0.15s; }
    .pwa-install-banner-btn:hover { filter: brightness(1.15); }
    .pwa-install-banner-later { position: relative; background: none; border: none; color: rgba(74, 52, 3, 0.75); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }
    .pwa-install-banner-later:hover { color: #4a3403; text-decoration: underline; }
    @media (max-width: 479px) { .pwa-install-banner { flex-wrap: wrap; } .pwa-install-banner-text { flex-basis: calc(100% - 60px); } }
    @media (prefers-reduced-motion: reduce) { .pwa-install-banner::after { animation: none; } }
</style>
<div id="pwa-install-banner" class="pwa-install-banner" wire:ignore style="display:none;">
    <span class="pwa-install-banner-icon"><i class="fa-solid fa-mobile-screen-button"></i></span>
    <p id="pwa-install-text" class="pwa-install-banner-text">Pasang aplikasi HRIS di perangkat Anda agar bisa menerima notifikasi dan lebih mudah diakses.</p>
    <button type="button" id="pwa-install-btn" class="pwa-install-banner-btn" onclick="pwaInstallApp()">Pasang</button>
    <button type="button" class="pwa-install-banner-later" onclick="pwaDismissInstallBanner()">Nanti</button>
</div>

{{-- Panduan pasang untuk iPhone/iPad (Safari): iOS tidak punya prompt pasang bawaan seperti Chromium, jadi
tombol banner membuka panduan ini. Dibuka/ditutup lewat JS (display none <-> flex), wire:ignore supaya tidak di-morph Livewire. --}}
<div id="pwa-ios-guide" class="modal-overlay" wire:ignore style="display:none;" onclick="pwaCloseIosGuide()">
    <div class="modal-box" style="max-width:420px;" onclick="event.stopPropagation()">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">Pasang Aplikasi di iPhone</h3>
            <button type="button" onclick="pwaCloseIosGuide()" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:16px;padding:4px;" aria-label="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <p id="pwa-ios-guide-warning" style="display:none;font-size:12px;font-weight:600;color:#b45309;background:rgba(217,119,6,0.12);border-radius:8px;padding:8px 10px;margin:0 0 12px;">
            Anda sedang tidak memakai Safari. Buka halaman ini di Safari dulu agar pemasangan berjalan lancar.
        </p>

        <ol style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:12px;">
            <li style="display:flex;gap:12px;align-items:flex-start;">
                <span class="pwa-tile-icon navy" style="flex-shrink:0;"><i class="fa-brands fa-safari"></i></span>
                <p style="font-size:13px;color:var(--text-heading);margin:2px 0 0;"><strong>1.</strong> Buka halaman ini lewat <strong>Safari</strong>.</p>
            </li>
            <li style="display:flex;gap:12px;align-items:flex-start;">
                <span class="pwa-tile-icon navy" style="flex-shrink:0;"><i class="fa-solid fa-arrow-up-from-bracket"></i></span>
                <p style="font-size:13px;color:var(--text-heading);margin:2px 0 0;"><strong>2.</strong> Ketuk ikon <strong>Bagikan</strong> (kotak dengan panah ke atas) di bagian bawah layar. Di iPad, ikonnya ada di pojok kanan atas.</p>
            </li>
            <li style="display:flex;gap:12px;align-items:flex-start;">
                <span class="pwa-tile-icon navy" style="flex-shrink:0;"><i class="fa-solid fa-square-plus"></i></span>
                <p style="font-size:13px;color:var(--text-heading);margin:2px 0 0;"><strong>3.</strong> Gulir menu ke bawah, lalu pilih <strong>Tambah ke Layar Utama</strong>.</p>
            </li>
            <li style="display:flex;gap:12px;align-items:flex-start;">
                <span class="pwa-tile-icon navy" style="flex-shrink:0;"><i class="fa-solid fa-check"></i></span>
                <p style="font-size:13px;color:var(--text-heading);margin:2px 0 0;"><strong>4.</strong> Ketuk <strong>Tambah</strong> di pojok kanan atas. Ikon HRIS akan muncul di layar utama; buka aplikasi dari sana.</p>
            </li>
        </ol>

        <p style="font-size:11px;color:var(--text-faint);margin:14px 0 0;">
            Notifikasi di iPhone hanya bisa aktif setelah aplikasi dibuka dari ikon di layar utama (butuh iOS 16.4 atau lebih baru).
        </p>

        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
            <button type="button" onclick="pwaCloseIosGuide()" class="btn-primary">Mengerti</button>
        </div>
    </div>
</div>

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
    {{-- Tombol putih "Dashboard" di welcome card: latarnya tetap putih di dark mode, tapi var(--brand) berubah jadi
    biru muda sehingga teksnya nyaris tak terbaca. Dikunci navy (sama dengan .data-diri-btn di app.css; inline
    supaya langsung berlaku tanpa build). Tombol emas "Profil Saya" punya warna sendiri. --}}
    <style>.welcome-actions .data-diri-btn:not(.gold) { color: #042c6c; }</style>
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
                @if (auth()->user()->canAccessDashboardAnda())
                    <a href="{{ route('dashboard-anda') }}" wire:navigate class="data-diri-btn">
                        <i class="fa-solid fa-chart-line"></i> Dashboard
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

    {{-- PRESENSI: status hari ini (jadwal + libur/cuti/izin + jam presensi asli), digabung sama
    info Extra/Double Shift kalau jadwalnya tipe Shift dan hari ini kena (instruksi user
    2026-10-16 - dulu kartu "Extra Shift/Double Shift Hari Ini" terpisah di bawahnya, sekarang
    jadi varian warna sky kartu yang sama, lihat today-attendance-card.blade.php). --}}
    <x-today-attendance-card :status="$this->todayAttendanceStatus" :can-sync="true" id="absen-section" class="bento-absensi" />

    {{-- Cuti Tahunan + Jumlah Izin + Jumlah Lembur, berdampingan dengan presensi di layar lebar.
    Dua variasi ukuran (LeaveBalanceCard/AttendanceStatCards menghitung style-nya di server lewat
    prop `compact`, jadi tidak bisa satu markup untuk semua breakpoint) — visibility diatur lewat
    class .bento-side-stats(-compact) di app.css, bukan Tailwind md:hidden (lihat komentar di sana).
    Sama seperti card Pengajuan Absensi Saya, ikut disembunyikan tanpa permission itu - isinya
    murni statistik cuti/izin/lembur (domain Pengajuan Absensi), bukan hal terpisah. --}}
    @if ($this->canPengajuanAbsensi)
        {{-- Karyawan berjadwal Shift punya 4 kartu (ada Extra Shift): HP 2 kolom, desktop 4 kolom;
        tanpa Extra Shift tetap 3 kolom (kelas .summary-cols-4 didefinisikan di x-attendance-summary-cards). --}}
        @php $summaryClass = $this->canJadwalShiftSaya ? ' summary-cols-4' : ''; @endphp
        <div class="bento-side-stats-compact{{ $summaryClass }}">
            <x-attendance-summary-cards :compact="true" />
        </div>
        <div class="bento-side-stats{{ $summaryClass }}">
            <x-attendance-summary-cards />
        </div>
    @endif

    {{-- Jadwal Shift Saya ditaruh SEBELUM Tugas/Pelatihan (instruksi user 2026-09-16) - kalender
    shift pribadi lebih sering dicek harian daripada daftar tugas/pelatihan, jadi diprioritaskan
    lebih atas di dashboard. --}}
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
        {{-- Kalender diperbesar di desktop (2026-09-20): kolom kalender 1/2 lebar container (dulu 1/5,
        app.css: 1fr 4fr), jarak antar sel & huruf lebih besar. Inline supaya tidak perlu build CSS. --}}
        <style>
            /* Legenda/keterangan shift diperbesar (2026-09-21): HP 13px, desktop 16px, jarak antar baris lebih lega. */
            .jadwal-shift-side-col > div:first-child { gap: 9px !important; }
            .jadwal-shift-legend-item { font-size: 13px !important; gap: 8px !important; }
            .jadwal-shift-legend-item > span:first-child { width: 10px !important; height: 10px !important; }
            @media (min-width: 768px) {
                .jadwal-shift-side-col > div:first-child { gap: 12px !important; }
                .jadwal-shift-legend-item { font-size: 16px !important; gap: 10px !important; }
                .jadwal-shift-legend-item > span:first-child { width: 12px !important; height: 12px !important; }
                .jadwal-shift-calendar-wrap { grid-template-columns: 9fr 11fr; } /* kalender ~45% lebar (dulu 1fr 1fr) */
                .jadwal-shift-grid { gap: 8px !important; }
                .jadwal-shift-grid > span { font-size: 14px !important; }
                .jadwal-shift-cell { font-size: 18px !important; border-radius: 14px !important; gap: 3px !important; }
                .jadwal-shift-cell .jadwal-shift-cell-code { font-size: 20px !important; }
            }
        </style>
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
                                class="jadwal-shift-cell"
                                @if ($day['is_current_month'] && $schedule && $schedule['label']) title="{{ $schedule['label'] }}" @endif
                                style="aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:7px;font-size:10.5px;gap:1px;position:relative;
                                    {{ $day['is_current_month'] ? "background:{$cellBg};color:{$cellColor};font-weight:700;" : 'color:var(--text-faint);' }}
                                    {{ $day['is_today'] ? 'box-shadow:inset 0 0 0 1.5px var(--brand);' : '' }}">
                                @if ($day['is_swap'])
                                    <span title="Terkait pengajuan tukar shift — lihat Riwayat di bawah."
                                        style="position:absolute;top:1px;right:1px;color:#b45309;font-size:8px;line-height:1;">
                                        <i class="fa-solid fa-right-left"></i>
                                    </span>
                                @endif
                                {{ $day['date']->day }}
                                @if ($day['is_current_month'] && $state === 'scheduled' && $schedule['code'])
                                    <span class="jadwal-shift-cell-code" style="font-size:12px;font-weight:800;letter-spacing:0.2px;line-height:1;">{{ $schedule['code'] }}</span>
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

                <livewire:presensi.shift-swap-ongoing-banner :key="'shift-swap-ongoing-banner'" />

                <div class="jadwal-shift-swap-actions">
                    <livewire:presensi.shift-swap-request-modal :key="'shift-swap-modal'" />
                    <livewire:presensi.shift-swap-history-sheet :key="'shift-swap-history'" />
                </div>
            </div>
        </div>
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
                <a href="{{ route('tugas.papan', 'saya') }}" wire:navigate class="pwa-list-row" style="text-decoration:none;border-left:4px solid {{ $accent }};padding-left:12px;">
                    <div style="flex:1;min-width:0;">
                        <p class="pwa-row-title">{{ \Illuminate\Support\Str::limit($task->requirement, 50) }}</p>
                        <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:700;color:{{ $accent }};margin-top:4px;">
                            {{ $isOverdue ? 'Overdue · ' . $dueDate->translatedFormat('j M') : $statusLabel($task->status) }}
                        </span>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @empty
                @if ($this->myBoardTaskCounts->isEmpty())
                    <div class="pwa-list-row" style="opacity:0.7;border-left:4px solid var(--border);padding-left:12px;">
                        <div style="flex:1;min-width:0;">
                            <p class="pwa-row-title">Belum ada tugas</p>
                            <span style="font-size:11.5px;color:var(--text-muted);">Buat tugas baru lewat menu Tugas</span>
                        </div>
                    </div>
                @endif
            @endforelse
            {{-- Board (bukan per card): nama board + jumlah card belum selesai yang ditugaskan ke user. --}}
            @foreach ($this->myBoardTaskCounts as $boardProject)
                <a href="{{ route('tugas.papan.board', $boardProject) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;border-left:4px solid {{ $boardProject->color }};padding-left:12px;">
                    <div style="flex:1;min-width:0;">
                        <p class="pwa-row-title"><i class="fa-solid fa-diagram-project" style="font-size:11px;color:{{ $boardProject->color }};margin-right:5px;"></i>{{ \Illuminate\Support\Str::limit($boardProject->name, 40) }}</p>
                        <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:700;color:{{ $boardProject->color }};margin-top:4px;">
                            {{ $boardProject->unfinished_count }} card belum selesai
                        </span>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @endforeach
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

    @if ($this->canJadwalShiftTim)
        <livewire:presensi.team-shift-schedule-card :key="'team-shift-schedule'" />
    @endif

    {{-- Desktop + mobile — tidak ada di bottom-nav. --}}
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
// ── Banner "Pasang aplikasi" ──
// Chromium (Android/desktop): pakai event beforeinstallprompt yang ditangkap layout (window.__pwaInstallPrompt),
// tombol Pasang memanggil prompt() bawaan browser. iOS/Safari tidak punya event itu - cuma bisa lewat menu
// Bagikan, jadi banner menampilkan petunjuk teks tanpa tombol Pasang. Sudah terpasang (mode standalone) = tidak tampil.
// Seperti banner notifikasi, "Nanti" hanya menyembunyikan untuk tampilan ini (tidak disimpan) - muncul lagi di kunjungan berikutnya.
function pwaIsStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function pwaIsIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function pwaShowInstallBanner(mode) {
    const banner = document.getElementById('pwa-install-banner');
    if (! banner || pwaIsStandalone()) return;

    const installBtn = document.getElementById('pwa-install-btn');
    if (mode === 'ios') {
        document.getElementById('pwa-install-text').textContent = 'Agar bisa menerima notifikasi dan mudah diakses, pasang aplikasi ke layar utama iPhone Anda. Ketuk "Cara Pasang" untuk panduannya.';
        installBtn.textContent = 'Cara Pasang';
        installBtn.style.display = '';
    } else {
        installBtn.textContent = 'Pasang';
        installBtn.style.display = '';
    }
    banner.style.display = 'flex';
}

function pwaDismissInstallBanner() {
    const banner = document.getElementById('pwa-install-banner');
    if (banner) banner.style.display = 'none';
}

function pwaOpenIosGuide() {
    // Safari asli: UA iOS tanpa token browser lain. Chrome/Firefox/Edge/Opera iOS (CriOS/FxiOS/EdgiOS/OPiOS)
    // dapat peringatan buka di Safari.
    const notSafari = /crios|fxios|edgios|opios|gsa\//i.test(navigator.userAgent);
    document.getElementById('pwa-ios-guide-warning').style.display = notSafari ? 'block' : 'none';
    document.getElementById('pwa-ios-guide').style.display = 'flex';
}

function pwaCloseIosGuide() {
    document.getElementById('pwa-ios-guide').style.display = 'none';
}

async function pwaInstallApp() {
    const promptEvent = window.__pwaInstallPrompt;
    if (! promptEvent) {
        // Tidak ada prompt bawaan (iOS) -> buka panduan manual.
        if (pwaIsIos()) pwaOpenIosGuide();
        return;
    }

    promptEvent.prompt();
    await promptEvent.userChoice;
    // Event hanya bisa dipakai sekali; kalau user menolak, browser akan menembakkannya lagi nanti.
    window.__pwaInstallPrompt = null;
    pwaDismissInstallBanner();
}

function pwaInitInstallBanner() {
    if (! document.getElementById('pwa-install-banner') || pwaIsStandalone()) return;

    if (window.__pwaInstallPrompt) {
        pwaShowInstallBanner('prompt');
    } else if (pwaIsIos()) {
        pwaShowInstallBanner('ios');
    }
}

// Banner Beranda dirender ulang tiap wire:navigate ke sini, jadi init dipanggil di load pertama DAN livewire:navigated.
document.addEventListener('DOMContentLoaded', pwaInitInstallBanner);
document.addEventListener('livewire:navigated', pwaInitInstallBanner);
// Event datang terlambat (setelah Beranda terbuka) / aplikasi baru saja dipasang.
window.addEventListener('pwa-install-available', () => pwaShowInstallBanner('prompt'));
window.addEventListener('pwa-installed', pwaDismissInstallBanner);

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
