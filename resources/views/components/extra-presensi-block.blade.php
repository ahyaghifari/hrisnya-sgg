@props(['extra'])
@php
    // Jam scan Extra/Double Shift hari ini (extra_shift_attendance_records) - tampil di kanan jam
    // masuk/keluar presensi normal pada kartu Presensi Hari Ini, atas = masuk, bawah = keluar.
    // Sumber: ExtraShiftTodayCard::build() ('attendance', null kalau belum ada scan).
    $att = $extra['attendance'] ?? null;
    $jam = fn (?string $time) => $time ? substr($time, 0, 5) : '-';
@endphp
@once
    <style>
        /* Petir kuning "menyala": berkedip pelan + glow, transparansi dijaga <= 50% (instruksi user). */
        .extra-bolt {
            color: #facc15;
            opacity: 0.5;
            animation: extra-bolt-flash 1.6s ease-in-out infinite;
        }
        @keyframes extra-bolt-flash {
            0%, 100% { opacity: 0.2; filter: drop-shadow(0 0 0 rgba(250, 204, 21, 0)); transform: scale(0.95); }
            45%      { opacity: 0.5; filter: drop-shadow(0 0 5px rgba(250, 204, 21, 0.9)); transform: scale(1.1); }
            60%      { opacity: 0.3; filter: drop-shadow(0 0 2px rgba(250, 204, 21, 0.5)); transform: scale(1); }
        }
        @media (prefers-reduced-motion: reduce) {
            .extra-bolt { animation: none; }
        }
    </style>
@endonce

<div {{ $attributes->merge(['style' => 'border-left:1px solid rgba(255,255,255,0.3);padding-left:14px;min-width:92px;']) }}>
    <p style="display:flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:rgba(255,255,255,0.85);margin:0 0 5px;white-space:nowrap;">
        <i class="fa-solid fa-bolt extra-bolt" aria-hidden="true"></i> Presensi Extra
    </p>
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;">
        <span style="font-size:11px;color:rgba(255,255,255,0.6);">Masuk</span>
        <strong style="font-size:17px;font-weight:800;color:#fff;letter-spacing:-0.3px;">{{ $jam($att?->check_in_time) }}</strong>
    </div>
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-top:2px;">
        <span style="font-size:11px;color:rgba(255,255,255,0.6);">Keluar</span>
        <strong style="font-size:17px;font-weight:800;color:#fff;letter-spacing:-0.3px;">{{ $jam($att?->check_out_time) }}</strong>
    </div>
</div>
