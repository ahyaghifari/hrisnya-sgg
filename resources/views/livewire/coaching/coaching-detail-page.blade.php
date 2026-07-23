<div>
<x-page-header title="Detail Coaching" :subtitle="$record->title" :back="route('coaching')" />

@php
    $status = \App\Enum\CoachingStatus::tryFrom($record->status);
    $statusBadge = match ($record->status) {
        'completed' => 'badge-approved',
        'rejected' => 'badge-rejected',
        default => 'badge-pending',
    };
@endphp

@if ($this->canReview)
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
        <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Bukti Sudah Diajukan — Perlu Ditinjau</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" wire:click="approve" wire:confirm="Tandai coaching ini selesai?" class="btn-primary">
                <i class="fa-solid fa-check"></i> Tandai Selesai
            </button>
            <button type="button" wire:click="reject" wire:confirm="Tolak bukti coaching ini?" class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);">
                <i class="fa-solid fa-xmark"></i> Tolak
            </button>
        </div>
    </div>
@endif

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Karyawan</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->user?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Coach</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->coachBy?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Deadline Perbaikan</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->improvement_due_date ? \Illuminate\Support\Carbon::parse($record->improvement_due_date)->translatedFormat('d F Y') : '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
        <span style="font-size:12.5px;color:var(--text-muted);">Status</span>
        <span class="badge {{ $statusBadge }}">{{ $status?->getLabel() ?? $record->status }}</span>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;margin-bottom:16px;">
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Tujuan & Kondisi</h3>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Tujuan Coaching</p>
        <p style="font-size:13px;color:var(--text-body);margin:0 0 12px;white-space:pre-line;">{{ $record->goals ?: '-' }}</p>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Kondisi Karyawan</p>
        <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $record->employee_condition ?: '-' }}</p>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Solusi & Tindakan</h3>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Solusi</p>
        <p style="font-size:13px;color:var(--text-body);margin:0 0 12px;white-space:pre-line;">{{ $record->solution ?: '-' }}</p>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Tindakan Perbaikan</p>
        <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $record->improvement_action ?: '-' }}</p>
    </div>
</div>

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
    <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Bukti Pelaksanaan</h3>

    @forelse ($record->grouped_proofs as $group)
        <div style="margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span style="font-size:13px;font-weight:700;color:var(--text-heading);">{{ \Illuminate\Support\Carbon::parse($group['date'])->translatedFormat('d M Y') }}</span>
                <span class="badge" style="background:var(--surface-alt);color:var(--text-body);">{{ \App\Enum\CoachingStatus::tryFrom($group['status'])?->getLabel() ?? $group['status'] }}</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4" style="gap:8px;">
                @foreach ($group['proofs'] as $proof)
                    <a href="{{ $proof->file_url }}" target="_blank" style="display:block;border-radius:10px;overflow:hidden;border:1px solid var(--border-soft);">
                        <img src="{{ $proof->file_url }}" alt="Bukti" style="width:100%;height:110px;object-fit:cover;">
                    </a>
                @endforeach
            </div>
        </div>
    @empty
        <p style="font-size:12.5px;color:var(--text-muted);">Belum ada bukti pelaksanaan yang diunggah.</p>
    @endforelse
</div>
</div>
