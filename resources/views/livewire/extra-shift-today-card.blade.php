@php
    $jam = fn (?string $time) => $time ? substr($time, 0, 5) : '-';
    $label = ($data['is_double_shift'] ?? false) ? 'Double Shift' : 'Extra Shift';
@endphp
<div>
@if ($data)
    {{-- Gaya kartu disamakan dengan .absen-card (Presensi Hari Ini) - padding/radius/dekorasi
    a-deco-1/2/3 sama, cuma background-nya sky TERANG (.absen-card-sky, lihat app.css) - bukan
    gradient gelap kayak Presensi - jadi teksnya pakai warna gelap di sini, bukan putih. --}}
    <div class="extra-shift-card-inner absen-card absen-card-sky">
        <div class="a-deco-1"></div><div class="a-deco-2"></div><div class="a-deco-3"></div>

        <div style="position:relative;z-index:1;display:flex;align-items:flex-start;gap:12px;">
            <div style="width:34px;height:34px;border-radius:10px;background:rgba(3,105,161,0.14);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-clock-rotate-left" style="color:#0369a1;font-size:14px;"></i>
            </div>
            <div style="min-width:0;">
                <p style="font-size:12.5px;font-weight:700;color:#0369a1;margin:0;">
                    Hari ini Anda ada {{ $label }}
                </p>
                @if ($data['is_double_shift'])
                    <p style="font-size:11px;color:#0c4a6e;opacity:0.75;margin:2px 0 0;">tanpa jasa - akibat tukar shift</p>
                @elseif ($data['assigned_by'] && $data['assigned_by']['name'])
                    <p style="font-size:11px;color:#0c4a6e;opacity:0.75;margin:2px 0 0;">
                        Ditugaskan oleh <strong>{{ $data['assigned_by']['name'] }}</strong> · {{ $data['assigned_by']['at']->translatedFormat('d M Y, H:i') }}
                    </p>
                @endif
            </div>
        </div>

        <div style="position:relative;z-index:1;">
            @if ($data['mode'] === 'adjacent')
                <p style="font-size:13px;color:#0c4a6e;margin:0;">
                    Shift-nya nempel, cukup scan <strong>2x</strong>:
                    masuk jam <strong>{{ $jam($data['earlier']->start_time) }}</strong>,
                    pulang jam <strong>{{ $jam($data['later']->end_time) }}</strong>.
                </p>
            @elseif ($data['mode'] === 'skip')
                <p style="font-size:13px;color:#0c4a6e;margin:0 0 6px;">
                    Ada jeda antar shift, WAJIB scan <strong>4x</strong> - jangan cuma masuk-pulang sekali:
                </p>
                <ul style="font-size:13px;color:#0c4a6e;margin:0;padding-left:18px;">
                    <li>Masuk <strong>{{ $jam($data['earlier']->start_time) }}</strong>, pulang <strong>{{ $jam($data['earlier']->end_time) }}</strong> ({{ $data['earlier']->name }})</li>
                    <li>Absen LAGI jam <strong>{{ $jam($data['later']->start_time) }}</strong>, pulang <strong>{{ $jam($data['later']->end_time) }}</strong> ({{ $data['later']->name }})</li>
                </ul>
            @else
                <p style="font-size:13px;color:#0c4a6e;margin:0;">
                    Jadwal normal Anda hari ini libur, tapi ada tugas tambahan
                    <strong>{{ $data['shift']->name }}</strong>:
                    masuk jam <strong>{{ $jam($data['shift']->start_time) }}</strong>,
                    pulang jam <strong>{{ $jam($data['shift']->end_time) }}</strong>.
                </p>
            @endif
        </div>
    </div>
@endif
</div>
