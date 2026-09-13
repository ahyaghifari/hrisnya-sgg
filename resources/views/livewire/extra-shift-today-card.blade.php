@php
    $jam = fn (?string $time) => $time ? substr($time, 0, 5) : '-';
    $label = ($data['is_double_shift'] ?? false) ? 'Double Shift' : 'Extra Shift';
@endphp
<div>
@if ($data)
    <div class="extra-shift-card-inner" style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:14px;padding:16px 18px;display:flex;align-items:flex-start;gap:12px;">
        <div style="width:34px;height:34px;border-radius:10px;background:rgba(2,132,199,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa-solid fa-clock-rotate-left" style="color:#0369a1;font-size:14px;"></i>
        </div>
        <div style="min-width:0;">
            <p style="font-size:12.5px;font-weight:700;color:#0369a1;margin:0 0 4px;">
                Hari ini Anda ada {{ $label }}
                @if ($data['is_double_shift'])
                    <span style="font-weight:500;color:var(--text-muted);">(tanpa jasa - akibat tukar shift)</span>
                @endif
            </p>

            @if ($data['mode'] === 'adjacent')
                <p style="font-size:13px;color:var(--text-body);margin:0;">
                    Shift-nya nempel, cukup scan <strong>2x</strong>:
                    masuk jam <strong>{{ $jam($data['earlier']->start_time) }}</strong>,
                    pulang jam <strong>{{ $jam($data['later']->end_time) }}</strong>.
                </p>
            @elseif ($data['mode'] === 'skip')
                <p style="font-size:13px;color:var(--text-body);margin:0 0 4px;">
                    Ada jeda antar shift, WAJIB scan <strong>4x</strong> - jangan cuma masuk-pulang sekali:
                </p>
                <ul style="font-size:13px;color:var(--text-body);margin:0;padding-left:18px;">
                    <li>Masuk <strong>{{ $jam($data['earlier']->start_time) }}</strong>, pulang <strong>{{ $jam($data['earlier']->end_time) }}</strong> ({{ $data['earlier']->name }})</li>
                    <li>Absen LAGI jam <strong>{{ $jam($data['later']->start_time) }}</strong>, pulang <strong>{{ $jam($data['later']->end_time) }}</strong> ({{ $data['later']->name }})</li>
                </ul>
            @else
                <p style="font-size:13px;color:var(--text-body);margin:0;">
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
