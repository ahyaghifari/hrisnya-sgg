@php
    // $isCreate & $locked dikirim dari view pemanggil (create/edit-jabatan-page.blade.php).
    $disabled = $locked;
@endphp
<div class="form-card">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-sitemap" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Struktur Organisasi</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px;">
        @if ($this->canBrowseAllCompanies)
            <div>
                <label class="form-label">Perusahaan <span style="color:var(--danger-text);">*</span></label>
                <select wire:model.live="company_id" class="form-input" @disabled($disabled)>
                    <option value="">Pilih perusahaan</option>
                    @foreach ($this->companyOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('company_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label class="form-label">Departemen <span style="color:var(--danger-text);">*</span></label>
            <select wire:model.live="department_id" class="form-input" @disabled($disabled || ! $company_id)>
                <option value="">Pilih departemen</option>
                @foreach ($this->departmentOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            @error('department_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">Divisi</label>
            <p style="font-size:11px;color:var(--text-muted);margin:-2px 0 6px;">Opsional - isi kalau jabatan ini di dalam divisi tertentu.</p>
            <select wire:model.live="division_id" class="form-input" @disabled($disabled || ! $department_id)>
                <option value="">Tidak ada</option>
                @foreach ($this->divisionOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label">Sub Divisi</label>
            <p style="font-size:11px;color:var(--text-muted);margin:-2px 0 6px;">Opsional - isi kalau jabatan ini di dalam sub divisi tertentu.</p>
            <select wire:model="sub_division_id" class="form-input" @disabled($disabled || ! $division_id)>
                <option value="">Tidak ada</option>
                @foreach ($this->subDivisionOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="form-card" style="margin-top:16px;">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-id-card-clip" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Detail Jabatan</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div>
            <label class="form-label">Nama Jabatan <span style="color:var(--danger-text);">*</span></label>
            <input type="text" wire:model="name" class="form-input" placeholder="Contoh: HR Officer, Finance Manager" @disabled($disabled)>
            @error('name') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">Atasan Langsung</label>
            <p style="font-size:11px;color:var(--text-muted);margin:-2px 0 6px;">Opsional.</p>
            <select wire:model="parent_id" class="form-input" @disabled($disabled || ! $company_id)>
                <option value="">Tidak ada</option>
                @foreach ($this->parentOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label">Deskripsi Jabatan</label>
            <textarea wire:model="description" class="form-input" rows="3" @disabled($disabled)></textarea>
            @error('description') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">Kualifikasi Jabatan</label>
            {{-- wire:ignore WAJIB — Trix generate toolbar sendiri yang tidak boleh di-morph Livewire. --}}
            <div wire:ignore>
                <input type="hidden" id="trix-qualification" wire:model="qualification" value="{{ $qualification }}">
                <trix-editor input="trix-qualification" style="min-height:100px;" @if($disabled) contenteditable="false" @endif></trix-editor>
            </div>
            @error('qualification') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

<div class="form-card" style="margin-top:16px;">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-scale-balanced" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Job Evaluation</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
        <div>
            <label class="form-label">Pangkat</label>
            <select wire:model.live="pangkat_id" class="form-input" @disabled($disabled || ! $company_id)>
                <option value="">Tidak ada</option>
                @foreach ($this->pangkatOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label">Golongan</label>
            <p style="font-size:11px;color:var(--text-muted);margin:-2px 0 6px;">Otomatis ikut Pangkat yang dipilih.</p>
            <input type="text" value="{{ $this->selectedPangkat?->golongan ?? '-' }}" class="form-input" disabled>
        </div>

        <div>
            <label class="form-label">Kelas</label>
            <select wire:model="kelas_id" class="form-input" @disabled($disabled || ! $company_id)>
                <option value="">Tidak ada</option>
                @foreach ($this->kelasOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label">Skor Penilaian Jabatan</label>
            <input type="number" wire:model="job_evaluation_score" min="0" class="form-input" @disabled($disabled)>
            @error('job_evaluation_score') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

<div class="form-card" style="margin-top:16px;">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-briefcase" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Analisis Beban Kerja</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div>
            <label class="form-label">Kuota Karyawan @if ($isCreate)<span style="color:var(--danger-text);">*</span>@endif</label>
            <p style="font-size:11px;color:var(--text-muted);margin:-2px 0 6px;">Cuma bisa diisi saat pengajuan baru. Perubahan kuota setelahnya lewat pengajuan Permintaan Tenaga Kerja.</p>
            <input type="number" wire:model="planned_manpower" min="0" class="form-input" @disabled($disabled || ! $isCreate)>
            @error('planned_manpower') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
