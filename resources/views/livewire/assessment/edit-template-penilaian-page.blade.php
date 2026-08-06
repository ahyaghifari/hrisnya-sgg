<div class="form-page-wrap">
    <x-page-header title="Edit Template Penilaian" :back="route('penilaian.template')" />

    <form wire:submit="submit">
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-file-lines" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Detail Template</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Nama Template <span style="color:var(--danger-text);">*</span></label>
                    <input type="text" class="form-input" wire:model="name">
                    @error('name') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Tipe Penilaian <span style="color:var(--danger-text);">*</span></label>
                    <select class="form-input" wire:model="type_id">
                        <option value="">Pilih tipe penilaian</option>
                        @foreach ($this->assessmentTypes as $id => $typeName)
                            <option value="{{ $id }}">{{ $typeName }}</option>
                        @endforeach
                    </select>
                    @error('type_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Jabatan <span style="color:var(--danger-text);">*</span></label>
                    <select class="form-input" wire:model="job_position_id">
                        <option value="">Pilih jabatan</option>
                        @foreach ($this->jobPositions as $id => $jobPositionName)
                            <option value="{{ $id }}">{{ $jobPositionName }}</option>
                        @endforeach
                    </select>
                    @error('job_position_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-input" rows="3" wire:model="description"></textarea>
                </div>

                <div class="toggle-wrapper" wire:click="$toggle('is_active')">
                    <div class="toggle {{ $is_active ? 'on' : '' }}"></div>
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0;">Template Aktif</p>
                </div>
            </div>
        </div>

        <div class="form-card" style="margin-top:16px;">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-list-check" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Indikator Penilaian</span>
            </div>
            @include('livewire.assessment.partials.template-item-rows')
        </div>

        <div style="display:flex;align-items:center;justify-content:flex-end;margin-top:20px;">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan Perubahan</button>
        </div>
    </form>
</div>
