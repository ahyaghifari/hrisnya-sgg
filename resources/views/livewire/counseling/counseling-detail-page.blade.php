<div>
<x-page-header title="Detail Counseling" :subtitle="$record->user?->name" :back="route('counseling.kelola')" />

@php
    $status = \App\Enum\CounselingStatus::tryFrom($record->status);
    $statusBadge = $record->status === 'done' ? 'badge-approved' : 'badge-pending';
@endphp

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Karyawan</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->user?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Tanggal Dilaporkan</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->created_at?->translatedFormat('d F Y H:i') }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
        <span style="font-size:12.5px;color:var(--text-muted);">Status</span>
        <span class="badge {{ $statusBadge }}">{{ $status?->getLabel() ?? $record->status }}</span>
    </div>
    <div style="padding-top:12px;">
        <span style="font-size:12.5px;color:var(--text-muted);display:block;margin-bottom:6px;">Isi Keluhan</span>
        <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $record->issue }}</p>
    </div>
</div>

@if ($this->canRespond)
    <form wire:submit="submitResponse" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Balas Keluhan</h3>
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div>
                <label class="form-label">Response <span style="color:var(--danger-text);">*</span></label>
                <textarea wire:model="response" class="form-input" rows="3"></textarea>
                @error('response') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Solution <span style="color:var(--danger-text);">*</span></label>
                <textarea wire:model="solution" class="form-input" rows="3"></textarea>
                @error('solution') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            <div style="text-align:right;">
                <button type="submit" class="btn-primary" wire:confirm="Kirim balasan ini? Status akan ditandai selesai."><i class="fa-solid fa-paper-plane"></i> Kirim Balasan</button>
            </div>
        </div>
    </form>
@elseif ($record->status === 'done')
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Balasan</h3>
        <div style="padding-bottom:10px;">
            <span style="font-size:12.5px;color:var(--text-muted);display:block;margin-bottom:6px;">Response</span>
            <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $record->response }}</p>
        </div>
        <div style="padding-bottom:10px;">
            <span style="font-size:12.5px;color:var(--text-muted);display:block;margin-bottom:6px;">Solution</span>
            <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $record->solution }}</p>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding-top:10px;border-top:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Dibalas Oleh</span>
            <span style="font-size:12.5px;color:var(--text-body);">{{ $record->respondedBy?->name ?? '-' }} &middot; {{ $record->responded_at?->translatedFormat('d M Y H:i') }}</span>
        </div>
    </div>
@endif
</div>
