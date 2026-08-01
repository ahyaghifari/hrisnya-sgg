<div class="form-page-wrap">
    <x-page-header title="Tambah Penilaian" :back="route('penilaian.daftar')" />

    <form wire:submit="submit">
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-user-plus" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Tunjuk Karyawan</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Jadwal Penilaian <span style="color:var(--danger-text);">*</span></label>
                    <select class="form-input" wire:model.live="assessment_id">
                        <option value="">Pilih jadwal</option>
                        @foreach ($this->jadwalOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('assessment_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Karyawan <span style="color:var(--danger-text);">*</span></label>
                    <select class="form-input" wire:model="target_id">
                        <option value="">Pilih karyawan</option>
                        @foreach ($this->karyawanOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('target_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:flex-end;margin-top:20px;">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Tambahkan</button>
        </div>
    </form>
</div>
