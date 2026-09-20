@php $isCreate = false; $locked = false; @endphp
<div>
<x-page-header title="Edit Jabatan" :subtitle="$record->name" :back="route('kepegawaian.jabatan')" />

@if ($record->approvalSnapshot)
    <x-approval-timeline :timeline="$this->timeline" />
@endif

@if ($this->canApprove)
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Menunggu Persetujuan Anda</p>
            <p style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">Kuota diajukan: <strong>{{ $record->planned_manpower }}</strong> orang &middot; Kuota sekarang: <strong>{{ $record->manpower ?? 0 }}</strong> orang</p>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="button" wire:click="openApprovalModal('reject')" class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);">
                <i class="fa-solid fa-xmark"></i> Tolak
            </button>
            <button type="button" wire:click="openApprovalModal('approve')" class="btn-primary">
                <i class="fa-solid fa-check"></i> Setujui
            </button>
        </div>
    </div>
@endif

<form wire:submit="save">
    @include('livewire.kepegawaian.partials.jabatan-form-fields')

    @unless ($locked)
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
            <a href="{{ route('kepegawaian.jabatan') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan</button>
        </div>
    @endunless
</form>

@if ($record->approvalSnapshot)
    <div style="margin-top:16px;">
        <x-approval-history-list :histories="$record->approvalHistories" />
    </div>
@endif

@if ($showApprovalModal)
    <div class="modal-overlay" wire:click="closeApprovalModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                {{ $approvalAction === 'approve' ? 'Setujui Pengajuan Jabatan' : 'Tolak Pengajuan Jabatan' }}
            </h3>

            <div style="display:flex;flex-direction:column;gap:12px;">
                @if ($approvalAction === 'approve')
                    <div>
                        <label class="form-label">Realisasi Kuota Karyawan</label>
                        <input type="number" min="0" class="form-input" wire:model="actualManpower">
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kuota sekarang: {{ $record->manpower ?? 0 }} &middot; Diajukan: {{ $record->planned_manpower }}</p>
                        @error('actualManpower') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="form-label">{{ $approvalAction === 'approve' ? 'Catatan Persetujuan' : 'Alasan Penolakan' }}</label>
                    <textarea class="form-input" rows="3" wire:model="approvalNote" placeholder="{{ $approvalAction === 'approve' ? 'Tambahkan catatan untuk persetujuan ini...' : 'Tambahkan alasan penolakan...' }}"></textarea>
                    @error('approvalNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                <button type="button" wire:click="closeApprovalModal" class="btn-ghost">Batal</button>
                <button type="button" wire:click="confirmApproval" class="btn-primary" style="{{ $approvalAction === 'reject' ? 'background:#dc2626;' : '' }}">
                    {{ $approvalAction === 'approve' ? 'Ya, Setujui' : 'Ya, Tolak' }}
                </button>
            </div>
        </div>
    </div>
@endif
</div>
