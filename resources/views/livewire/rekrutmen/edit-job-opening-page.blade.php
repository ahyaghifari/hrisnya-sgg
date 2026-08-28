<div>
<x-page-header title="Edit Lowongan Kerja" subtitle="Posisi, kuota, deskripsi, dan persyaratan lowongan" :back="route('rekrutmen.lowongan.show', $record)" />

<div style="margin-bottom:16px;display:flex;align-items:center;gap:10px;">
    <span style="font-size:12px;color:var(--text-muted);">Status:</span>
    <span class="badge {{ $this->status->getBadgeClass() }}">{{ $this->status->getLabel() }}</span>
</div>

<div>
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
                <select wire:model.live="job_position_id" class="form-input">
                    <option value="">Pilih posisi jabatan</option>
                    @foreach ($this->jobPositionOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('job_position_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Nama Lain Lowongan</label>
                <input type="text" wire:model="alias" class="form-input" placeholder="Contoh: Perawat (kalau nama jabatan asli terlalu panjang)">
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Opsional - nama ringkas yang tampil di sistem Rekrutmen, menggantikan nama jabatan asli. Kosong = pakai nama jabatan.</p>
                @error('alias') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Kuota</label>
                <input type="number" value="{{ $quota }}" class="form-input" disabled>
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengikuti jumlah dari Permintaan Tenaga Kerja yang disetujui, tidak bisa diubah di sini.</p>
            </div>

            <div>
                <label class="form-label">Batas Jumlah Pelamar</label>
                <input type="number" wire:model="max_applicants" min="1" class="form-input" placeholder="Kosong = tidak dibatasi">
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Dipakai sistem Rekrutmen buat berhenti nerima lamaran begitu jumlah ini tercapai. Kosongkan kalau tidak mau dibatasi.</p>
                @error('max_applicants') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Batas Tanggal Lamaran</label>
                <input type="date" wire:model="application_deadline" class="form-input">
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Dipakai sistem Rekrutmen buat tutup penerimaan lamaran otomatis setelah tanggal ini. Kosongkan kalau tidak ada batas waktu.</p>
                @error('application_deadline') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Deskripsi Lowongan</label>
                <textarea wire:model="description" class="form-input" rows="4" placeholder="Deskripsi pekerjaan..."></textarea>
                @error('description') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Persyaratan Lowongan</label>
                <div wire:ignore>
                    <input type="hidden" id="trix-requirements" wire:model="requirements" value="{{ $requirements }}">
                    <trix-editor input="trix-requirements" style="min-height:100px;"></trix-editor>
                </div>
                @error('requirements') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('rekrutmen.lowongan.show', $record) }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" wire:click="saveDraft" class="btn-ghost">
                <i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i>
                {{ $this->status === \App\Enum\Recruitment\JobOpeningStatus::WAITING ? 'Simpan Draft' : 'Simpan Perubahan' }}
            </button>

            @if ($this->status === \App\Enum\Recruitment\JobOpeningStatus::OPEN)
                <button type="button" wire:click="close" wire:confirm="Tutup lowongan ini? Lowongan tidak akan tampil lagi di sistem Rekrutmen." class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);"><i class="fa-solid fa-lock" style="font-size:12px;"></i> Tutup Lowongan</button>
            @else
                <button type="button" wire:click="publish" wire:confirm="Buka lowongan ini? Lowongan akan langsung tampil di sistem Rekrutmen." class="btn-primary"><i class="fa-solid fa-bullhorn" style="font-size:12px;"></i> Buka Lowongan</button>
            @endif
        </div>
    </div>
</div>
</div>
