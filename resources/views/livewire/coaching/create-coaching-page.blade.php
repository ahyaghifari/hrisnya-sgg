<div>
<x-page-header title="Buat Coaching" subtitle="Catat sesi pembinaan untuk karyawan" :back="route('coaching')" />

<form wire:submit="save" style="max-width:720px;">
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-id-card" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Informasi Karyawan</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            @if ($this->canBrowseAllCompanies)
                <div>
                    <label class="form-label">Perusahaan <span style="color:var(--danger-text);">*</span></label>
                    <select wire:model.live="companyId" class="form-input">
                        <option value="">Pilih perusahaan</option>
                        @foreach ($this->companyOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="form-label">Karyawan <span style="color:var(--danger-text);">*</span></label>
                <select wire:model="user_id" class="form-input" @disabled(! $companyId)>
                    <option value="">Pilih karyawan</option>
                    @foreach ($this->employeeOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('user_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Coach <span style="color:var(--danger-text);">*</span></label>
                <select wire:model="coach_by" class="form-input" @disabled(! $companyId)>
                    <option value="">Pilih coach</option>
                    @foreach ($this->employeeOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('coach_by') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Judul <span style="color:var(--danger-text);">*</span></label>
                <input type="text" wire:model="title" class="form-input" placeholder="Judul coaching...">
                @error('title') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Deadline Perbaikan <span style="color:var(--danger-text);">*</span></label>
                <input type="date" wire:model="improvement_due_date" class="form-input">
                @error('improvement_due_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-bullseye" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Tujuan & Kondisi</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Tujuan Coaching</label>
                <textarea wire:model="goals" class="form-input" rows="3"></textarea>
            </div>
            <div>
                <label class="form-label">Kondisi Karyawan</label>
                <textarea wire:model="employee_condition" class="form-input" rows="3"></textarea>
            </div>
        </div>
    </div>

    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-lightbulb" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Solusi & Tindakan Perbaikan</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Solusi</label>
                <textarea wire:model="solution" class="form-input" rows="3"></textarea>
            </div>
            <div>
                <label class="form-label">Tindakan Perbaikan</label>
                <textarea wire:model="improvement_action" class="form-input" rows="3"></textarea>
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('coaching') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan</button>
    </div>
</form>
</div>
