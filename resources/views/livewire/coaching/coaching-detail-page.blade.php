<div>
<x-page-header title="Detail Coaching" :subtitle="$record->title" :back="$this->backRoute" />

<div class="card-surface accent accent-navy" style="margin-bottom:16px;">
    <div class="section-head-icon" style="margin-bottom:8px;">
        <div class="section-icon"><i class="fa-solid fa-clipboard-user"></i></div>
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Informasi Coaching</p>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Karyawan</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->user?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Coach</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->coachBy?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Dibuat Oleh</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->createdBy?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
        <span style="font-size:12.5px;color:var(--text-muted);">Diberikan Pada</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);text-align:right;">
            {{ $record->created_at->translatedFormat('l, d F Y \p\u\k\u\l H:i') }}
            <span style="display:block;font-size:11.5px;font-weight:400;color:var(--text-muted);">{{ $record->created_at->diffForHumans() }}</span>
        </span>
    </div>
</div>

@php
    $dueDate = $record->improvement_due_date ? \Illuminate\Support\Carbon::parse($record->improvement_due_date)->startOfDay() : null;
    $daysLeft = $dueDate ? (int) today()->diffInDays($dueDate, false) : null;
    $dueNote = match (true) {
        $daysLeft === null => null,
        $daysLeft === 0 => ['Hari ini', 'badge-pending'],
        $daysLeft > 0 => ["{$daysLeft} hari lagi", 'badge-pending'],
        default => [abs($daysLeft).' hari lalu', 'badge-cancelled'],
    };
@endphp

<div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;margin-bottom:16px;">
    <div class="card-surface accent accent-indigo">
        <div class="section-head-icon" style="margin-bottom:14px;">
            <div class="section-icon"><i class="fa-solid fa-bullseye"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Tujuan & Kondisi</p>
        </div>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Judul</p>
        <p style="font-size:13px;color:var(--text-body);margin:0 0 12px;white-space:pre-line;">{{ $record->title ?: '-' }}</p>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Tujuan Coaching</p>
        <p style="font-size:13px;color:var(--text-body);margin:0 0 12px;white-space:pre-line;">{{ $record->goals ?: '-' }}</p>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Kondisi Karyawan</p>
        <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $record->employee_condition ?: '-' }}</p>
    </div>

    <div class="card-surface accent accent-green">
        <div class="section-head-icon" style="margin-bottom:14px;">
            <div class="section-icon"><i class="fa-solid fa-lightbulb"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Solusi & Tindakan Perbaikan</p>
        </div>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Solusi</p>
        <p style="font-size:13px;color:var(--text-body);margin:0 0 12px;white-space:pre-line;">{{ $record->solution ?: '-' }}</p>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Tindakan Perbaikan</p>
        <p style="font-size:13px;color:var(--text-body);margin:0 0 12px;white-space:pre-line;">{{ $record->improvement_action ?: '-' }}</p>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Deadline Perbaikan</p>
        <p style="font-size:13px;color:var(--text-body);margin:0;">
            {{ $dueDate ? $dueDate->translatedFormat('l, d F Y') : '-' }}
            @if ($dueNote)
                <span class="badge {{ $dueNote[1] }}" style="margin-left:6px;">{{ $dueNote[0] }}</span>
            @endif
        </p>
    </div>
</div>

@if ($this->isCoach || $this->isUserCreate)
    <div class="card-surface accent accent-navy" style="margin-bottom:16px;">
        <div class="section-head-icon" style="margin-bottom:14px;">
            <div class="section-icon"><i class="fa-solid fa-clipboard-check"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Tindak Lanjut</p>
        </div>

        <label class="form-label">Kondisi Terkini</label>
        <select class="form-input" wire:model="followUpStatus" style="margin-bottom:10px;">
            <option value="">-- Pilih --</option>
            @foreach (\App\Enum\CoachingFollowUpStatus::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
            @endforeach
        </select>
        @error('followUpStatus') <p style="color:var(--danger-text);font-size:12px;margin:-6px 0 10px;">{{ $message }}</p> @enderror

        <label class="form-label">Catatan</label>
        <textarea class="form-input" rows="3" placeholder="Catatan kondisi karyawan saat ini..." wire:model="followUpNote" style="margin-bottom:10px;"></textarea>

        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <button type="button" wire:click="saveFollowUp" class="btn-primary">Simpan</button>

            @if ($record->follow_up_status === \App\Enum\CoachingFollowUpStatus::NEEDS_FOLLOW_UP)
                <a href="{{ route('coaching.create', ['user_id' => $record->user_id]) }}" wire:navigate class="btn-ghost">
                    <i class="fa-solid fa-plus"></i> Buat Coaching Lanjutan
                </a>
            @elseif ($record->follow_up_status === \App\Enum\CoachingFollowUpStatus::NEEDS_SP)
                <a href="{{ route('sp.create', ['user_id' => $record->user_id, 'coaching_id' => $record->id]) }}" wire:navigate class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);">
                    <i class="fa-solid fa-triangle-exclamation"></i> Buat SP
                </a>
            @endif
        </div>
        @if ($record->follow_up_at)
            <p style="font-size:11px;color:var(--text-muted);margin:8px 0 0;">Terakhir diisi {{ $record->follow_up_at->diffForHumans() }}</p>
        @endif

        {{-- SP terkait - read-only, cuma info. SP dibuat dari sisi SP sendiri (bisa pilih
        coaching ini sebagai sumbernya), bukan dari sini (instruksi user 2026-09-23: Coaching &
        SP dipisah total). --}}
        @php $linkedSp = $this->linkedSp; @endphp
        @if ($linkedSp)
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft);display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <span style="font-size:12.5px;color:var(--text-muted);">SP Terkait</span>
                <span style="font-size:13px;font-weight:600;color:var(--text-body);">
                    {{ $linkedSp->sp_level->getLabel() }} &middot; {{ $linkedSp->reason?->name }}
                </span>
            </div>
        @endif
    </div>
@endif

</div>
