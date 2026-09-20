<div>
<x-page-header title="Ajukan Penambahan Kuota" :subtitle="$record->name" :back="route('kepegawaian.jabatan')" />

@if ($draft)
    <div style="background:var(--warning-soft-bg,rgba(217,119,6,0.1));border:1px solid var(--warning-soft-border,rgba(217,119,6,0.3));border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-file-circle-exclamation" style="color:#d97706;"></i>
        <p style="font-size:12.5px;color:var(--text-body);margin:0;">Ada draft pengajuan kuota yang belum diajukan untuk jabatan ini — lanjutkan isian di bawah.</p>
    </div>
@endif

<form wire:submit="submit">
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-users-gear" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Detail Pengajuan</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Jabatan</label>
                <input type="text" value="{{ $record->name }}" class="form-input" disabled>
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kuota saat ini: {{ $record->manpower ?? 0 }}</p>
            </div>

            <div>
                <label class="form-label">Jumlah Penambahan <span style="color:var(--danger-text);">*</span></label>
                <input type="number" wire:model="quantity" min="1" class="form-input">
                @error('quantity') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Catatan</label>
                <textarea wire:model="notes" class="form-input" rows="3" placeholder="Alasan penambahan kuota..."></textarea>
                @error('notes') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Lampiran</label>
                <input type="file" wire:model="file" class="form-input">
                @error('file') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                <div wire:loading wire:target="file" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengunggah...</div>
                @if ($draft?->file_path)
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($draft->file_path) }}" target="_blank" style="font-size:11.5px;color:var(--brand);margin-top:6px;display:inline-block;">
                        <i class="fa-solid fa-paperclip"></i> Lihat lampiran draft saat ini
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('kepegawaian.jabatan') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <div style="display:flex;gap:10px;">
            @if ($draft)
                <button type="button" wire:click="discardDraft" wire:confirm="Batalkan draft pengajuan kuota ini? Draft akan dihapus." class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);"><i class="fa-solid fa-trash" style="font-size:12px;"></i> Batalkan</button>
            @endif
            <button type="button" wire:click="saveDraft" class="btn-ghost"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan Draft</button>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan</button>
        </div>
    </div>
</form>
</div>
