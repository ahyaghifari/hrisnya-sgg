<div>
@push('head')
    @filamentStyles
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
@endpush

<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Detail Pengajuan</h1>
    </div>
    <a href="{{ route('my-attendance-requests') }}" wire:navigate class="text-sm font-semibold text-gray-600 hover:text-gray-900">
        &larr; Kembali
    </a>
</div>

<!-- Timeline Status -->
<x-approval-timeline :timeline="$this->timeline" />

<!-- Aksi Approval (hanya muncul untuk approver step yang sedang aktif) -->
@if ($this->canApprove)
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h3 class="text-sm font-bold text-slate-800 mb-4">Aksi Persetujuan</h3>

        @if ($pendingAction)
            <div style="display:flex;flex-direction:column;gap:12px;">
                <label class="form-label">Catatan <span style="color:#ef4444;">*</span></label>
                <textarea class="form-input" rows="3" placeholder="Tambahkan catatan..." wire:model="note"></textarea>
                @error('note') <p style="color:#ef4444;font-size:12px;">{{ $message }}</p> @enderror

                <div style="display:flex;gap:10px;">
                    <button type="button" wire:click="confirmAction" class="btn-primary">Konfirmasi</button>
                    <button type="button" wire:click="cancelChoice" class="btn-ghost">Batal</button>
                </div>
            </div>
        @else
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" wire:click="chooseAction('approve')" class="btn-primary">
                    <i class="fa-solid fa-check"></i> Setujui
                </button>
                <button type="button" wire:click="chooseAction('reject')" class="btn-ghost" style="color:#ef4444;border-color:#fecaca;">
                    <i class="fa-solid fa-xmark"></i> Tolak
                </button>
                <button type="button" wire:click="chooseAction('cancel')" class="btn-ghost">
                    <i class="fa-solid fa-ban"></i> Batalkan
                </button>
            </div>
        @endif
    </div>
@endif

{{ $this->getSchema('infolist') }}

@push('scripts')
    @filamentScripts
@endpush
</div>
