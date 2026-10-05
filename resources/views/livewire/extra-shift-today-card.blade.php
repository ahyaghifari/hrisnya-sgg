@php
    $jam = fn (?string $time) => $time ? substr($time, 0, 5) : '-';
    $label = ($data['is_double_shift'] ?? false) ? 'Double Shift' : 'Extra Shift';

    // Urutan scan sungguhan (bukan dekorasi) - masuk/pulang harus dalam urutan ini, makanya
    // ditampilkan sebagai timeline bernomor, bukan paragraf lepas.
    $scanSteps = ($data['mode'] ?? null) === 'adjacent'
        ? [['Masuk', $data['earlier']->start_time ?? null, $data['earlier']->name ?? null], ['Pulang', $data['later']->end_time ?? null, $data['later']->name ?? null]]
        : (($data['mode'] ?? null) === 'skip'
            ? [
                ['Masuk', $data['earlier']->start_time ?? null, $data['earlier']->name ?? null],
                ['Pulang', $data['earlier']->end_time ?? null, $data['earlier']->name ?? null],
                ['Masuk lagi', $data['later']->start_time ?? null, $data['later']->name ?? null],
                ['Pulang', $data['later']->end_time ?? null, $data['later']->name ?? null],
            ]
            : []);
@endphp
<div>
@if ($data)
    {{-- Gaya kartu disamakan dengan .absen-card (Presensi Hari Ini) - padding/radius/dekorasi
    a-deco-1/2/3 sama, cuma background-nya sky TERANG (.absen-card-sky, lihat app.css) - bukan
    gradient gelap kayak Presensi - jadi teksnya pakai warna gelap di sini, bukan putih. --}}
    <div class="extra-shift-card-inner absen-card absen-card-sky">
        <div class="a-deco-1"></div><div class="a-deco-2"></div><div class="a-deco-3"></div>

        <div style="position:relative;z-index:1;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
            <div style="display:flex;align-items:flex-start;gap:12px;min-width:0;">
                <div style="width:34px;height:34px;border-radius:10px;background:rgba(3,105,161,0.14);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-clock-rotate-left" style="color:#0369a1;font-size:14px;"></i>
                </div>
                <div style="min-width:0;">
                    <p style="font-size:12.5px;font-weight:700;color:#0369a1;margin:0;">
                        Hari ini Anda ada {{ $label }}
                    </p>
                    @if ($data['is_double_shift'])
                        <p style="font-size:11px;color:#0c4a6e;opacity:0.75;margin:2px 0 0;">akibat tukar shift</p>
                    @elseif ($data['assigned_by'] && $data['assigned_by']['name'])
                        <p style="font-size:11px;color:#0c4a6e;opacity:0.75;margin:2px 0 0;">
                            Ditugaskan oleh <strong>{{ $data['assigned_by']['name'] }}</strong> · {{ $data['assigned_by']['at']->translatedFormat('d M Y, H:i') }}
                        </p>
                    @endif
                </div>
            </div>

            <span class="badge" style="flex-shrink:0;white-space:nowrap;
                {{ $data['is_double_shift'] ? 'background:var(--warn-soft-bg);color:var(--warn-soft-text);' : 'background:var(--info-soft-bg);color:var(--info-soft-text);' }}">
                {{ $label }}
            </span>
        </div>

        <div style="position:relative;z-index:1;">
            @if ($data['mode'] === 'skip')
                <p style="font-size:11px;color:#0c4a6e;opacity:0.75;margin:0 0 10px;">
                    Ada jeda antar shift, WAJIB scan <strong>4x</strong>:
                </p>
            @elseif ($data['mode'] === 'adjacent')
                <p style="font-size:11px;color:#0c4a6e;opacity:0.75;margin:0 0 10px;">
                    Shift-nya nempel, cukup scan <strong>2x</strong>:
                </p>
            @endif

            @if ($scanSteps)
                <div style="display:flex;flex-direction:column;">
                    @foreach ($scanSteps as $step)
                        <div style="display:flex;gap:10px;">
                            <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                                <div style="width:22px;height:22px;border-radius:50%;background:#fff;border:2px solid #0369a1;display:flex;align-items:center;justify-content:center;font-size:9.5px;font-weight:800;color:#0369a1;flex-shrink:0;">
                                    {{ $loop->iteration }}
                                </div>
                                @unless ($loop->last)
                                    <div style="width:2px;flex:1;min-height:12px;background:#7dd3fc;margin:2px 0;"></div>
                                @endunless
                            </div>
                            <div style="padding-bottom:{{ $loop->last ? '0' : '10px' }};min-width:0;">
                                <p style="font-size:12.5px;font-weight:700;color:#0c4a6e;margin:0;">
                                    {{ $step[0] }} <span style="font-weight:800;">{{ $jam($step[1]) }}</span>
                                </p>
                                <p style="font-size:11px;color:#0c4a6e;opacity:0.7;margin:1px 0 0;">{{ $step[2] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
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
