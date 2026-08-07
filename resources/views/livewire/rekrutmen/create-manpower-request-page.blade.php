<div>
<x-page-header title="Ajukan Permintaan Tenaga Kerja" subtitle="Penambahan kuota, karyawan, atau penggantian karyawan" :back="route('kepegawaian.permintaan')" />

<form wire:submit="submit">
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-right-left" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Informasi Permintaan</span>
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
                <label class="form-label">Tipe Permintaan <span style="color:var(--danger-text);">*</span></label>
                <select wire:model="type" class="form-input">
                    <option value="">Pilih tipe permintaan</option>
                    @foreach (\App\Enum\ManpowerRequestType::cases() as $requestType)
                        <option value="{{ $requestType->value }}">{{ $requestType->getLabel() }}</option>
                    @endforeach
                </select>
                @error('type') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Jabatan <span style="color:var(--danger-text);">*</span></label>
                <select wire:model.live="job_position_id" class="form-input" @disabled(! $companyId)>
                    <option value="">Pilih jabatan</option>
                    @foreach ($this->jobPositionOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('job_position_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Jumlah <span style="color:var(--danger-text);">*</span></label>
                <input type="number" wire:model="quantity" min="1" class="form-input">
                @if ($job_position_id)
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kuota tersedia: {{ $this->availableQuota }}</p>
                @endif
                @error('quantity') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Catatan</label>
                <textarea wire:model="notes" class="form-input" rows="3" placeholder="Catatan tambahan..."></textarea>
                @error('notes') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Lampiran</label>
                <input type="file" wire:model="file" class="form-input">
                @error('file') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                <div wire:loading wire:target="file" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengunggah...</div>
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('kepegawaian.permintaan') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <div style="display:flex;gap:10px;">
            <button type="button" wire:click="saveDraft" class="btn-ghost"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan Draft</button>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan</button>
        </div>
    </div>
</form>
</div>
