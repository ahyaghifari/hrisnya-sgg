<div>
<x-page-header title="Edit Lowongan Kerja" subtitle="Posisi, kuota, deskripsi, dan persyaratan lowongan" :back="route('rekrutmen.lowongan.show', $record)" />

<div style="max-width:720px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
    <span style="font-size:12px;color:var(--text-muted);">Status:</span>
    <span class="badge {{ $this->status->getBadgeClass() }}">{{ $this->status->getLabel() }}</span>
</div>

<div style="max-width:720px;">
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-briefcase" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Detail Lowongan</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            @if ($this->canBrowseAllCompanies)
                <div>
                    <label class="form-label">Perusahaan</label>
                    <input type="text" class="form-input" value="{{ $record->company?->name }}" disabled>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Perusahaan mengikuti Permintaan Tenaga Kerja asal lowongan ini, tidak bisa diubah di sini.</p>
                </div>
            @endif

            <div>
                <label class="form-label">Posisi Jabatan <span style="color:var(--danger-text);">*</span></label>
                <select wire:model="job_position_id" class="form-input">
                    <option value="">Pilih posisi jabatan</option>
                    @foreach ($this->jobPositionOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('job_position_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Kuota <span style="color:var(--danger-text);">*</span></label>
                <input type="number" wire:model="quota" min="1" class="form-input">
                @error('quota') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Deskripsi Lowongan</label>
                <textarea wire:model="description" class="form-input" rows="4" placeholder="Deskripsi pekerjaan..."></textarea>
                @error('description') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Persyaratan Lowongan</label>
                <textarea wire:model="requirements" class="form-input" rows="4" placeholder="Persyaratan kandidat..."></textarea>
                @error('requirements') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('rekrutmen.lowongan.show', $record) }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" wire:click="saveDraft" class="btn-ghost"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan Draft</button>

            @if ($this->status === \App\Enum\Recruitment\JobOpeningStatus::OPEN)
                <button type="button" wire:click="close" wire:confirm="Tutup lowongan ini? Lowongan tidak akan tampil lagi ke kandidat." class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);"><i class="fa-solid fa-lock" style="font-size:12px;"></i> Tutup Lowongan</button>
            @else
                <button type="button" wire:click="publish" wire:confirm="Buka lowongan ini? Lowongan akan langsung tampil ke kandidat." class="btn-primary"><i class="fa-solid fa-bullhorn" style="font-size:12px;"></i> Buka Lowongan</button>
            @endif
        </div>
    </div>
</div>
</div>
