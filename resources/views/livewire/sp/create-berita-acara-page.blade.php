<div>
<x-page-header title="Buat Berita Acara" subtitle="Awal proses klarifikasi karyawan" :back="route('sp')" />

<form wire:submit="save">
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-id-card" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Informasi</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Karyawan <span style="color:var(--danger-text);">*</span></label>
                <select data-tom-select data-placeholder="Pilih karyawan" wire:model="user_id" class="form-input">
                    <option value="">Pilih karyawan</option>
                    @foreach ($this->employeeOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('user_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Mengetahui <span style="color:var(--danger-text);">*</span></label>
                <select data-tom-select data-placeholder="Pilih yang mengetahui" wire:model="knows_by" class="form-input">
                    <option value="">Pilih yang mengetahui</option>
                    @foreach ($this->employeeOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('knows_by') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Nomor Berita Acara</label>
                <input type="text" wire:model="nomor" class="form-input" placeholder="mis. 001/BA/HC-GA/X/2026">
                @error('nomor') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-file-lines" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Dasar &amp; Kejadian</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Dasar Klarifikasi <span style="color:var(--danger-text);">*</span></label>
                @include('livewire.sp.partials.dasar-items-editor', ['idPrefix' => 'trix-ba-dasar'])
            </div>

            <div>
                <label class="form-label">Kejadian <span style="color:var(--danger-text);">*</span></label>
                <div wire:ignore>
                    <input type="hidden" id="trix-ba-kejadian" wire:model="kejadian" value="{{ $kejadian }}">
                    <trix-editor input="trix-ba-kejadian" style="min-height:100px;"></trix-editor>
                </div>
                @error('kejadian') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('sp') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan Draft</button>
    </div>
</form>
</div>
