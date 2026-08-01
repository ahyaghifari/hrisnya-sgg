<div>
    <label class="form-label">Nama Penilaian <span style="color:var(--danger-text);">*</span></label>
    <input type="text" class="form-input" wire:model="name" placeholder="Contoh: Penilaian Kinerja Semester 1 2026">
    @error('name') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
</div>

<div>
    <label class="form-label">Template Penilaian <span style="color:var(--danger-text);">*</span></label>
    <select class="form-input" wire:model="template_id">
        <option value="">Pilih template</option>
        @foreach ($this->templates as $id => $templateName)
            <option value="{{ $id }}">{{ $templateName }}</option>
        @endforeach
    </select>
    @error('template_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
</div>

<div>
    <label class="form-label">Jabatan Spesifik</label>
    <select class="form-input" wire:model="job_position_id">
        <option value="">Semua jabatan</option>
        @foreach ($this->jobPositions as $id => $jobName)
            <option value="{{ $id }}">{{ $jobName }}</option>
        @endforeach
    </select>
</div>

<div class="date-grid">
    <div>
        <label class="form-label">Tanggal Mulai <span style="color:var(--danger-text);">*</span></label>
        <input type="date" class="form-input" wire:model="start_date">
        @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="form-label">Tanggal Selesai <span style="color:var(--danger-text);">*</span></label>
        <input type="date" class="form-input" wire:model="end_date">
        @error('end_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
    </div>
</div>

<div class="toggle-wrapper" wire:click="$set('status', '{{ $status === 'active' ? 'inactive' : 'active' }}')">
    <div class="toggle {{ $status === 'active' ? 'on' : '' }}"></div>
    <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0;">Jadwal Aktif</p>
</div>
