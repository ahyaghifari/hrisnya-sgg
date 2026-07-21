{{-- Dipakai 2x: form "Tambah Pertemuan" & form "Detail Pertemuan" tiap accordion. Tipe
     Absensi sengaja tidak ada field-nya (dipaksa 'offline' di saveMeeting()). Tanggal Mulai/
     Selesai & Judul Pertemuan cuma muncul kalau course-nya lebih dari 1 hari
     (isSingleDayCourse) — course 1 hari cuma py 1 pertemuan, judulnya otomatis ikut judul
     pelatihan, langsung ke Trainer (wajib dipilih). --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
    @if (! $this->isSingleDayCourse)
        <div style="grid-column:1 / -1;">
            <label class="form-label">Judul Pertemuan</label>
            <input type="text" wire:model="meetingTitle" class="form-input">
            @error('meetingTitle') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
    @endif

    <div>
        <label class="form-label">Trainer <span style="color:var(--danger-text);">*</span></label>
        <select wire:model="meetingTrainerId" class="form-input">
            <option value="">-- Pilih Trainer --</option>
            @foreach ($this->trainerOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        @error('meetingTrainerId') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
    </div>

    @if (! $this->isSingleDayCourse)
        <div>
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" wire:model="meetingStartDate" class="form-input">
            @error('meetingStartDate') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">Tanggal Selesai</label>
            <input type="date" wire:model="meetingEndDate" class="form-input">
            @error('meetingEndDate') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
    @endif

    <div style="grid-column:1 / -1;">
        <label class="form-label">Deskripsi / Materi (opsional)</label>
        <textarea wire:model="meetingDescription" class="form-input" rows="3"></textarea>
    </div>
</div>
