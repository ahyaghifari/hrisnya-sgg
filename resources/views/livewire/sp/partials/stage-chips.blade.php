{{-- Lencana tahap Berita Acara -> Keputusan -> Surat Peringatan. Butuh $report (SpReport alur baru, boleh null) dan $sp (EmployeeSp, boleh null). --}}
@php
    $S = \App\Enum\Sp\SpReportStatus::class;
    $D = \App\Enum\Sp\SpReportDecision::class;

    // Tahap: done | current | pending | skip
    $invitation = $invitation ?? $report?->invitation;

    if (! $report && $invitation) {
        $stages = ['Undangan' => $invitation->isCancelled() ? 'skip' : 'current', 'Berita Acara' => 'pending', 'Keputusan' => 'pending', 'Surat Peringatan' => 'pending'];
    } elseif ($report) {
        $stages = ($report->invitation_id ? ['Undangan' => 'done'] : []) + [
            'Berita Acara' => $report->status === $S::SELESAI ? 'done' : 'current',
            'Keputusan' => ($report->keputusan || $sp) ? 'done' : ($report->status === $S::SELESAI ? 'current' : 'pending'),
            'Surat Peringatan' => $sp ? 'done' : ($report->keputusan === $D::LANJUT_SP ? 'current' : ($report->keputusan === $D::TIDAK_LAYAK_SP ? 'skip' : 'pending')),
        ];
    } else {
        $stages = ['Surat Peringatan' => 'done'];
    }

    $chip = fn (string $state) => match ($state) {
        'done' => 'background:#dcfce7;color:#15803d;',
        'current' => 'background:var(--brand);color:#fff;',
        'skip' => 'background:var(--surface-alt);color:var(--text-faint);text-decoration:line-through;',
        default => 'background:var(--surface-alt);color:var(--text-muted);',
    };
@endphp
<div style="display:flex;flex-wrap:wrap;gap:5px;{{ $chipsStyle ?? '' }}">
    @foreach ($stages as $label => $state)
        <span class="badge" style="{{ $chip($state) }}font-size:{{ $chipsSize ?? '10.5px' }};">
            @if ($state === 'done')<i class="fa-solid fa-check" style="font-size:9px;"></i>@endif {{ $label }}
        </span>
    @endforeach
</div>
