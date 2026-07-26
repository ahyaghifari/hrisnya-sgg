<div>
<x-page-header title="Detail Coaching" :subtitle="$record->title" :back="$this->backRoute" />

@php
    $status = \App\Enum\CoachingStatus::tryFrom($record->status);
    $statusBadge = match ($record->status) {
        'completed' => 'badge-approved',
        'rejected' => 'badge-rejected',
        default => 'badge-pending',
    };
@endphp

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

    @forelse ($record->proofs as $proof)
        @if ($loop->first)
            <div class="grid grid-cols-2 md:grid-cols-4" style="gap:8px;{{ $this->canUpload ? 'margin-bottom:16px;' : '' }}">
        @endif
            <div>
                <a href="{{ $proof->file_url }}" target="_blank" style="display:block;border-radius:10px;overflow:hidden;border:1px solid var(--border-soft);">
                    <img src="{{ $proof->file_url }}" alt="Bukti" style="width:100%;height:110px;object-fit:cover;">
                </a>
                <p style="font-size:10.5px;color:var(--text-muted);margin:4px 0 0;text-align:center;">
                    {{ $proof->created_at->translatedFormat('d M Y') }}
                    &middot; {{ \App\Enum\CoachingStatus::tryFrom($proof->status)?->getLabel() ?? $proof->status }}
                </p>
            </div>
        @if ($loop->last)
            </div>
        @endif
    @empty
        <p style="font-size:12.5px;color:var(--text-muted);{{ $this->canUpload ? 'margin-bottom:16px;' : '' }}">Belum ada bukti pelaksanaan yang diunggah.</p>
    @endforelse

    @if ($this->canUpload)
        <div style="{{ $record->proofs->isNotEmpty() ? 'padding-top:16px;border-top:1px solid var(--border-soft);' : '' }}">
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 10px;">
                @if ($record->status === 'rejected')
                    Bukti sebelumnya ditolak coach — upload bukti baru.
                @else
                    Foto/screenshot bukti sudah menjalankan tindakan perbaikan (JPG/PNG, maks 5MB per file).
                @endif
            </p>
            <form wire:submit="submitProofs" style="display:flex;flex-direction:column;gap:12px;">
                <input type="file" wire:model="proofs" multiple accept="image/png,image/jpeg" class="form-input">
                @error('proofs') <p style="color:var(--danger-text);font-size:12px;margin:0;">{{ $message }}</p> @enderror
                @error('proofs.*') <p style="color:var(--danger-text);font-size:12px;margin:0;">{{ $message }}</p> @enderror

                @if ($proofs)
                    <div class="grid grid-cols-2 md:grid-cols-4" style="gap:8px;">
                        @foreach ($proofs as $file)
                            <img src="{{ $file->temporaryUrl() }}" alt="Preview" style="width:100%;height:90px;object-fit:cover;border-radius:10px;border:1px solid var(--border-soft);">
                        @endforeach
                    </div>
                @endif

                <div style="text-align:right;">
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                        <i class="fa-solid fa-upload"></i>
                        {{ $record->status === 'rejected' ? 'Ajukan Bukti Kembali' : 'Ajukan Bukti' }}
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>

@if ($this->canReview)
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-top:16px;">
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
</div>
