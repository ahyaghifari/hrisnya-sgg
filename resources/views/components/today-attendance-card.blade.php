@props(['status'])
@php
    // Warna kartu ikut state (lihat ResolvesTodayAttendanceStatus): Libur = merah, Cuti = biru,
    // Izin Seharian = kuning, normal/izin biasa = hijau (default absen-card).
    $gradients = [
        'red'    => ['#dc2626', '#7f1d1d'],
        'blue'   => ['#1a56c4', '#0b2f6b'],
        'yellow' => ['#d97706', '#92400e'],
        'green'  => ['#047C54', '#024732'],
        'gray'   => ['#475569', '#1e293b'],
    ];
    [$from, $to] = $gradients[$status['color']] ?? $gradients['green'];
    $attendance = $status['attendance'];
    $formatJam = fn (?string $time) => $time ? \Carbon\Carbon::parse($time)->format('H:i') : '-';

    // Baris ke-2 kartu: tanggal + nama jadwal + detail (jam kerja/target menit), digabung 1
    // baris pakai "·" supaya ringkas - bukan 3 baris terpisah.
    $subLine = collect([now()->translatedFormat('d F Y'), $status['schedule_name'], $status['schedule_detail']])
        ->filter()
        ->implode(' · ');
@endphp
<div {{ $attributes->merge(['class' => 'absen-card', 'style' => "background:linear-gradient(135deg, {$from} 0%, {$to} 100%);"]) }}>
    <div class="a-deco-1"></div><div class="a-deco-2"></div><div class="a-deco-3"></div>

    <div style="position:relative;z-index:1;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
        <div style="min-width:0;">
            <span class="absen-label"><i class="fa-regular fa-clock"></i> Presensi Hari Ini</span>
            <p style="font-size:11.5px;color:rgba(255,255,255,0.7);margin-top:3px;">{{ $subLine }}</p>
        </div>
    </div>

    @if (in_array($status['state'], ['holiday', 'leave', 'full_day_permission', 'no_schedule']))
        {{-- Hari ini bukan hari kerja normal (libur/cuti/izin seharian), atau belum ada jadwal
        sama sekali - tidak relevan tampilkan jam masuk/keluar --}}
        <div style="position:relative;z-index:1;">
            <p style="font-size:16px;font-weight:800;color:#fff;">{{ $status['note'] }}</p>
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
</div>
