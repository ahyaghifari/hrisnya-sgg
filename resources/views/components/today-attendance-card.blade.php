@props(['status', 'canSync' => false])
@php
    // Warna kartu ikut state (lihat ResolvesTodayAttendanceStatus): Libur nasional/tanggal
    // merah = merah, Cuti = biru, Izin Seharian = kuning, Izin Sakit = pink, normal/izin biasa = hijau. 'plain'
    // = hari ini off cuma karena jadwal (bukan tanggal merah) -> kartu putih, teks netral.
    $gradients = [
        'red'    => ['#dc2626', '#7f1d1d'],
        'blue'   => ['#1a56c4', '#0b2f6b'],
        'yellow' => ['#d97706', '#92400e'],
        'pink'   => ['#be185d', '#831843'],
        'green'  => ['#047C54', '#024732'],
        'gray'   => ['#475569', '#1e293b'],
    ];
    $isPlain = ($status['color'] ?? null) === 'plain';
    [$from, $to] = $gradients[$status['color']] ?? $gradients['green'];
    $cardStyle = $isPlain
        ? 'background:var(--surface);border:1px solid var(--border);'
        : "background:linear-gradient(135deg, {$from} 0%, {$to} 100%);";
    $cLabel  = $isPlain ? 'var(--text-muted)'   : 'rgba(255,255,255,0.75)';
    $cSub    = $isPlain ? 'var(--text-faint)'   : 'rgba(255,255,255,0.7)';
    $cStrong = $isPlain ? 'var(--text-heading)' : '#fff';

    // Extra/Double Shift hari ini (jadwal tipe Shift saja, lihat
    // ResolvesTodayAttendanceStatus::resolveExtraShiftToday()) - dulu kartu terpisah
    // (extra-shift-today-card.blade.php), sekarang digabung ke kartu ini SEBAGAI VARIAN warna
    // supaya karyawan cukup lihat 1 kartu, bukan 2 bertumpuk (instruksi user 2026-10-16).
    // Gradient-nya SAMA PERSIS header widget "Jadwal Shift Saya" (.pwa-card-header-sky di
    // app.css: #0ea5e9 -> #0369a1) - instruksi user susulan, bukan varian pucat lagi - jadi
    // teksnya tetap putih (sama seperti kartu normal), bukan navy-di-atas-terang.
    $extra = $status['extra_shift'] ?? null;
    $isSky = (bool) $extra;
    if ($isSky) {
        $isPlain = false;
        $cardStyle = 'background:linear-gradient(130deg, #0ea5e9 0%, #0369a1 100%);';
    }

    $attendance = $status['attendance'];
    $formatJam = fn (?string $time) => $time ? \Carbon\Carbon::parse($time)->format('H:i') : '-';

    // Baris ke-2 kartu: tanggal + nama jadwal + detail (jam kerja/target menit), digabung 1
    // baris pakai "·" supaya ringkas - bukan 3 baris terpisah.
    $subLine = collect([\Carbon\Carbon::parse($status['date'] ?? today())->translatedFormat('d F Y'), $status['schedule_name'], $status['schedule_detail']])
        ->filter()
        ->implode(' · ');
@endphp
<div {{ $attributes->merge(['class' => 'absen-card', 'style' => $cardStyle]) }} x-data="{ showWindowModal: false }">
    @unless ($isPlain)
        <div class="a-deco-1"></div><div class="a-deco-2"></div><div class="a-deco-3"></div>
    @endunless

    {{-- Jendela presensi valid hari ini (kapan paling awal boleh scan masuk, kapan paling akhir
    boleh scan pulang), sumbernya persis konfigurasi jadwal (Fixed/Flexible/Shift) - lihat
    ResolvesTodayAttendanceStatus::resolveAttendanceWindow(). Tombol ikon info di pojok kanan
    atas (request user - bukan pill berteks lagi), buka modal detail lengkap. Alpine murni,
    tidak perlu round-trip Livewire karena datanya sudah ada di $status sejak render awal.
    Transisi modal niru pola merit-modal-* di employee-statistics.blade.php (fade overlay + pop
    di desktop, slide dari bawah di HP) tapi lewat Alpine x-transition karena elemen ini TETAP
    di DOM (x-show toggle display, bukan ditambah/dibuang Livewire @if) - CSS @keyframes/animation
    cuma jalan sekali saat mount, tidak re-trigger tiap dibuka-tutup. --}}
    {{-- Sync presensi sendiri hari ini (ResyncsOwnAttendance::syncMyToday) - di kiri tombol info
    kalau tombol info ada, kalau tidak ada nempel di pojok kanan atas. --}}
    @if ($canSync)
        <button type="button" wire:click="syncMyToday" wire:loading.attr="disabled" wire:target="syncMyToday" title="Sync presensi hari ini"
            style="position:absolute;top:12px;right:{{ ($status['attendance_window'] || $extra) ? '44px' : '12px' }};z-index:2;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,0.2);color:{{ $isPlain ? 'var(--text-heading)' : '#fff' }};border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:12px;">
            <i class="fa-solid fa-arrow-rotate-right" wire:loading.class="fa-spin" wire:target="syncMyToday"></i>
        </button>
    @endif

    @if ($status['attendance_window'] || $extra)
        @php
            $w = $status['attendance_window'];
            // $extra cuma pernah kebentuk buat jadwal tipe Shift (lihat
            // ResolvesTodayAttendanceStatus) - jadi kapan pun ada, $isFlexible pasti false,
            // aman dipakai gerbang bareng ($w bisa null di sini kalau hari itu extra_only,
            // jadwal normalnya libur - makanya isFlexible dicek null-safe).
            $isFlexible = $w && $w['type'] === 'flexible';
        @endphp
        <style>
            .win-modal-overlay, .win-modal-box { transition: opacity 0.18s ease-out, transform 0.22s cubic-bezier(0.2, 0.8, 0.2, 1); }
            .win-modal-enter-start.win-modal-overlay { opacity: 0; }
            .win-modal-enter-start.win-modal-box { opacity: 0; transform: translateY(14px) scale(0.97); }
            @media (max-width: 767.98px) {
                .win-modal-enter-start.win-modal-box { transform: translateY(100%); }
            }
            @media (prefers-reduced-motion: reduce) {
                .win-modal-overlay, .win-modal-box { transition: none; }
            }
        </style>

        <button type="button" @click="showWindowModal = true" title="Detail jendela presensi"
            style="position:absolute;top:12px;right:12px;z-index:2;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,0.2);color:#fff;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:13px;">
            <i class="fa-solid fa-circle-info"></i>
        </button>

        <div class="modal-overlay win-modal-overlay" x-show="showWindowModal" x-cloak
            x-transition:enter-start="win-modal-enter-start" x-transition:leave-end="win-modal-enter-start"
            @click="showWindowModal = false">
            <div class="modal-box win-modal-box" @click.stop
                x-transition:enter-start="win-modal-enter-start" x-transition:leave-end="win-modal-enter-start">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="pwa-tile-icon neutral"><i class="fa-solid fa-circle-info"></i></div>
                        <div>
                            <h3 style="font-size:15px;font-weight:800;color:var(--text-heading);margin:0;">Jendela Presensi</h3>
                            <p style="font-size:11px;color:var(--text-muted);margin:1px 0 0;">{{ $status['schedule_name'] ?? 'Jadwal hari ini' }}</p>
                        </div>
                    </div>
                    <button type="button" @click="showWindowModal = false" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:16px;padding:4px;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="display:flex;flex-direction:column;gap:10px;">
                    @if (! $isFlexible)
                        @if ($extra)
                            {{-- Double/Extra Shift hari ini - jendela normalnya (kalau ada) sudah
                            terwakili sebagai salah satu leg di bawah (mode adjacent/skip), jadi
                            body modal DIGANTI TOTAL ke rincian per-shift ini, bukan ditumpuk
                            dengan 4 baris jendela normal biasa (instruksi user 2026-10-16 - modal
                            harus "ikut update" pas ada double shift, bukan diam menampilkan
                            jendela shift normal seolah tidak ada apa-apa). --}}
                            @php
                                $legs = in_array($extra['mode'], ['adjacent', 'skip'], true)
                                    ? [$extra['earlier'], $extra['later']]
                                    : ($extra['mode'] === 'extra_only' ? [$extra['shift']] : []);
                                $scanNote = match ($extra['mode']) {
                                    'adjacent' => 'Shift nempel - cukup scan 2x (masuk shift pertama, pulang shift kedua).',
                                    'skip' => 'Ada jeda antar shift - WAJIB scan 4x (masuk & pulang tiap shift terpisah).',
                                    'extra_only' => 'Jadwal normal hari ini libur - ini tugas tambahan tersendiri.',
                                    default => null,
                                };
                            @endphp
                            <div style="display:flex;gap:12px;align-items:flex-start;">
                                <div class="pwa-tile-icon navy" style="flex-shrink:0;"><i class="fa-solid fa-bolt"></i></div>
                                <div>
                                    <p style="font-size:11px;color:var(--text-muted);margin:0;">{{ $extra['is_double_shift'] ? 'Double Shift' : 'Extra Shift' }} hari ini</p>
                                    @if ($scanNote)
                                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:2px 0 0;">{{ $scanNote }}</p>
                                    @endif
                                </div>
                            </div>
                            @foreach ($legs as $i => $leg)
                                <div style="display:flex;gap:12px;align-items:flex-start;">
                                    <div class="pwa-tile-icon {{ $i === 0 ? 'done' : 'gold' }}" style="flex-shrink:0;"><i class="fa-solid fa-clock"></i></div>
                                    <div>
                                        <p style="font-size:11px;color:var(--text-muted);margin:0;">{{ $leg->name }}</p>
                                        <p style="font-size:14.5px;font-weight:700;color:var(--text-heading);margin:2px 0 0;">{{ substr($leg->start_time, 0, 5) }} &ndash; {{ substr($leg->end_time, 0, 5) }}</p>
                                        <p style="font-size:11.5px;color:var(--text-muted);margin:1px 0 0;">
                                            Boleh masuk mulai {{ \Carbon\Carbon::createFromFormat('H:i:s', $leg->start_time)->subMinutes($leg->checkin_buffer_minutes)->format('H:i') }},
                                            batas pulang {{ \Carbon\Carbon::createFromFormat('H:i:s', $leg->end_time)->addMinutes($leg->checkout_buffer_minutes)->format('H:i') }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        @else
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div class="pwa-tile-icon navy" style="flex-shrink:0;"><i class="fa-solid fa-briefcase"></i></div>
                            <div>
                                <p style="font-size:11px;color:var(--text-muted);margin:0;">Jam kerja jadwal</p>
                                <p style="font-size:14.5px;font-weight:700;color:var(--text-heading);margin:2px 0 0;">{{ $w['start_time'] }} &ndash; {{ $w['end_time'] }}</p>
                            </div>
                        </div>
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div class="pwa-tile-icon done" style="flex-shrink:0;"><i class="fa-solid fa-right-to-bracket"></i></div>
                            <div>
                                <p style="font-size:11px;color:var(--text-muted);margin:0;">Boleh presensi masuk mulai</p>
                                <p style="font-size:14.5px;font-weight:700;color:var(--text-heading);margin:2px 0 0;">Jam {{ $w['from'] }}</p>
                                <p style="font-size:11.5px;color:var(--text-muted);margin:1px 0 0;">{{ $w['checkin_buffer_minutes'] }} menit lebih awal dari jam masuk</p>
                            </div>
                        </div>
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div class="pwa-tile-icon gold" style="flex-shrink:0;"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
                            <div>
                                <p style="font-size:11px;color:var(--text-muted);margin:0;">Batas akhir presensi pulang</p>
                                <p style="font-size:14.5px;font-weight:700;color:var(--text-heading);margin:2px 0 0;">Jam {{ $w['until'] }}</p>
                                <p style="font-size:11.5px;color:var(--text-muted);margin:1px 0 0;">{{ $w['checkout_buffer_minutes'] }} menit setelah jam pulang</p>
                            </div>
                        </div>
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div class="pwa-tile-icon danger" style="flex-shrink:0;"><i class="fa-solid fa-hourglass-end"></i></div>
                            <div>
                                <p style="font-size:11px;color:var(--text-muted);margin:0;">Toleransi keterlambatan</p>
                                <p style="font-size:14.5px;font-weight:700;color:var(--text-heading);margin:2px 0 0;">{{ $w['late_tolerance_minutes'] }} menit</p>
                                <p style="font-size:11.5px;color:var(--text-muted);margin:1px 0 0;">Dianggap terlambat kalau masuk setelah jam {{ $w['late_threshold'] }}</p>
                            </div>
                        </div>
                        @endif
                    @else
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div class="pwa-tile-icon teal" style="flex-shrink:0;"><i class="fa-solid fa-arrows-left-right"></i></div>
                            <div>
                                <p style="font-size:11px;color:var(--text-muted);margin:0;">Jendela presensi masuk</p>
                                <p style="font-size:14.5px;font-weight:700;color:var(--text-heading);margin:2px 0 0;">{{ $w['from'] }} &ndash; {{ $w['until'] }}</p>
                                <p style="font-size:11.5px;color:var(--text-muted);margin:1px 0 0;">Jadwal Fleksibel &mdash; presensi masuk hanya sah di rentang jam ini</p>
                            </div>
                        </div>
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div class="pwa-tile-icon {{ $w['late_checkin_deadline'] ? 'danger' : 'done' }}" style="flex-shrink:0;">
                                <i class="fa-solid {{ $w['late_checkin_deadline'] ? 'fa-hourglass-end' : 'fa-check' }}"></i>
                            </div>
                            <div>
                                <p style="font-size:11px;color:var(--text-muted);margin:0;">Batas jam masuk tanpa dianggap telat</p>
                                @if ($w['late_checkin_deadline'])
                                    <p style="font-size:14.5px;font-weight:700;color:var(--text-heading);margin:2px 0 0;">Jam {{ $w['late_checkin_deadline'] }}</p>
                                @else
                                    <p style="font-size:13px;color:var(--text-muted);margin:2px 0 0;">Tidak ada batas keterlambatan untuk hari ini</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div style="position:relative;z-index:1;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
        <div style="min-width:0;">
            <span class="absen-label" style="color:{{ $cLabel }};"><i class="fa-regular fa-clock"></i> Presensi Hari Ini</span>
            <p style="font-size:11.5px;color:{{ $cSub }};margin-top:3px;">{{ $subLine }}</p>
        </div>
        @if ($extra)
            <span class="badge" style="flex-shrink:0;white-space:nowrap;margin-right:{{ ($status['attendance_window'] || $extra) ? '34px' : '0' }};background:rgba(255,255,255,0.2);color:#fff;">
                <i class="fa-solid fa-bolt"></i> {{ $extra['is_double_shift'] ? 'Double Shift' : 'Extra Shift' }}
            </span>
        @endif
    </div>

    @if (in_array($status['state'], ['holiday', 'leave', 'full_day_permission', 'no_schedule']))
        {{-- Hari ini bukan hari kerja normal (libur/cuti/izin seharian), atau belum ada jadwal
        sama sekali - tidak relevan tampilkan jam masuk/keluar --}}
        <div style="position:relative;z-index:1;">
            <p style="font-size:16px;font-weight:800;color:{{ $cStrong }};">{{ $status['note'] }}</p>
        </div>
    @elseif ($attendance)
        <div style="position:relative;z-index:1;display:flex;gap:28px;">
            <div>
                <p style="font-size:11px;color:rgba(255,255,255,0.6);margin-bottom:2px;">Jam Masuk</p>
                <p style="font-size:24px;font-weight:800;color:#fff;letter-spacing:-0.5px;">{{ $formatJam($attendance->check_in_time) }}</p>
            </div>
            <div>
                <p style="font-size:11px;color:rgba(255,255,255,0.6);margin-bottom:2px;">Jam Keluar</p>
                <p style="font-size:24px;font-weight:800;color:#fff;letter-spacing:-0.5px;">{{ $formatJam($attendance->check_out_time) }}</p>
            </div>
        </div>
        @if ($status['flexible_min_work_label'])
            <div style="position:relative;z-index:1;font-size:12px;color:rgba(255,255,255,0.85);">
                Min. jam kerja: <strong>{{ $status['flexible_min_work_label'] }}</strong>
                @if ($status['flexible_target_checkout'] && ! $attendance->check_out_time)
                    · absen pulang jam <strong>{{ $status['flexible_target_checkout'] }}</strong> untuk mencapai target
                @endif
            </div>
        @endif
        <div style="position:relative;z-index:1;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="display:inline-block;padding:4px 12px;border-radius:99px;background:rgba(255,255,255,0.16);color:#fff;font-size:11.5px;font-weight:700;">
                {{ \App\Enum\AttendanceStatus::from($attendance->status)->getLabel() }}
            </span>
            @if ($attendance->late_minutes > 0)
                <span style="font-size:12px;color:rgba(255,255,255,0.75);">Terlambat {{ $status['late_minutes_label'] }}</span>
            @endif
            @if ($status['note'])
                {{-- Izin biasa (bukan izin seharian) tetap hijau, tapi keterangan izinnya tetap ditampilkan --}}
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:700;">{{ $status['note'] }}</span>
            @endif
        </div>
    @else
        <div style="position:relative;z-index:1;">
            <p style="font-size:14px;font-weight:600;color:#fff;">Belum ada data presensi</p>
            <p style="font-size:11.5px;color:rgba(255,255,255,0.65);margin-top:4px;">Karyawan belum melakukan presensi hari ini.</p>
            @if ($status['note'])
                <p style="font-size:12.5px;color:rgba(255,255,255,0.95);font-weight:700;margin-top:6px;">{{ $status['note'] }}</p>
            @endif
        </div>
    @endif

    {{-- Jam scan buat Extra/Double Shift hari ini - dulu isi kartu extra-shift-today-card.blade.php
    terpisah, sekarang ditempel di bawah kartu Presensi Hari Ini yang sama (instruksi user
    2026-10-16). Logika mode adjacent/skip/extra_only persis sama, lihat ExtraShiftTodayCard::build(). --}}
    @if ($extra)
        @php
            $jamExtra = fn (?string $time) => $time ? substr($time, 0, 5) : '-';
            $scanSteps = $extra['mode'] === 'adjacent'
                ? [['Masuk', $extra['earlier']->start_time ?? null, $extra['earlier']->name ?? null], ['Pulang', $extra['later']->end_time ?? null, $extra['later']->name ?? null]]
                : ($extra['mode'] === 'skip'
                    ? [
                        ['Masuk', $extra['earlier']->start_time ?? null, $extra['earlier']->name ?? null],
                        ['Pulang', $extra['earlier']->end_time ?? null, $extra['earlier']->name ?? null],
                        ['Masuk lagi', $extra['later']->start_time ?? null, $extra['later']->name ?? null],
                        ['Pulang', $extra['later']->end_time ?? null, $extra['later']->name ?? null],
                    ]
                    : []);
        @endphp
        <div style="position:relative;z-index:1;border-top:1px solid rgba(255,255,255,0.25);padding-top:10px;margin-top:2px;">
            @if ($extra['is_double_shift'])
                <p style="font-size:11px;color:rgba(255,255,255,0.8);margin:0 0 8px;">Double Shift</p>
            @elseif ($extra['assigned_by'] && $extra['assigned_by']['name'])
                <p style="font-size:11px;color:rgba(255,255,255,0.8);margin:0 0 8px;">
                    Ditugaskan oleh <strong>{{ $extra['assigned_by']['name'] }}</strong> · {{ $extra['assigned_by']['at']->translatedFormat('d M Y, H:i') }}
                </p>
            @endif

            @if ($extra['mode'] === 'skip')
                <p style="font-size:11px;color:rgba(255,255,255,0.8);margin:0 0 10px;">Ada jeda antar shift, WAJIB scan <strong>4x</strong> &mdash; jangan cuma masuk-pulang sekali:</p>
            @elseif ($extra['mode'] === 'adjacent')
                <p style="font-size:11px;color:rgba(255,255,255,0.8);margin:0 0 10px;">Shift-nya nempel, cukup scan <strong>2x</strong>:</p>
            @endif

            @if ($scanSteps)
                <div style="display:flex;flex-direction:column;">
                    @foreach ($scanSteps as $step)
                        <div style="display:flex;gap:10px;">
                            <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                                <div style="width:20px;height:20px;border-radius:50%;background:#0369a1;border:2px solid #fff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;color:#fff;flex-shrink:0;">
                                    {{ $loop->iteration }}
                                </div>
                                @unless ($loop->last)
                                    <div style="width:2px;flex:1;min-height:10px;background:rgba(255,255,255,0.4);margin:2px 0;"></div>
                                @endunless
                            </div>
                            <div style="padding-bottom:{{ $loop->last ? '0' : '8px' }};min-width:0;">
                                <p style="font-size:12px;font-weight:700;color:#fff;margin:0;">
                                    {{ $step[0] }} <span style="font-weight:800;">{{ $jamExtra($step[1]) }}</span>
                                </p>
                                <p style="font-size:10.5px;color:rgba(255,255,255,0.75);margin:1px 0 0;">{{ $step[2] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p style="font-size:12.5px;color:rgba(255,255,255,0.9);margin:0;">
                    Jadwal normal Anda hari ini libur, tapi ada tugas tambahan
                    <strong>{{ $extra['shift']->name }}</strong>:
                    masuk jam <strong>{{ $jamExtra($extra['shift']->start_time) }}</strong>,
                    pulang jam <strong>{{ $jamExtra($extra['shift']->end_time) }}</strong>.
                </p>
            @endif
        </div>
    @endif
</div>
