<div>
<x-page-header :title="$this->stationLabel()" subtitle="Input MCU Pelamar" :back="route('rekrutmen.pelamar.show', $jobApplicationId)" />

@php $C = \App\Livewire\Rekrutmen\McuFormPage::class; @endphp

@if ($status !== 'mcu')
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:24px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Pelamar ini tidak sedang di tahap MCU (status: {{ $status ?: 'tidak diketahui' }}).</p>
    </div>
@else
    <form wire:submit.prevent="simpan" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        @if ($filled)
            <span class="badge badge-approved" style="font-size:10px;margin-bottom:10px;display:inline-block;">Sudah terisi — Anda mengedit</span>
        @endif

        @if ($station === 'nurse')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px 16px;">
                @foreach ($C::NURSE_FIELDS as $field => [$type, $label])
                    @if ($type === 'bool')
                        <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;"><input type="checkbox" wire:model="form.{{ $field }}"> {{ $label }}</label>
                    @elseif ($type === 'textarea')
                        <div style="grid-column:1/-1;"><label class="form-label">{{ $label }}</label><textarea wire:model="form.{{ $field }}" class="form-input" rows="2"></textarea></div>
                    @else
                        <div><label class="form-label">{{ $label }}</label>
                            <input type="{{ $type === 'text' ? 'text' : 'number' }}" @if($type==='number') step="0.1" @endif wire:model="form.{{ $field }}" class="form-input">
                            @error('form.'.$field) <span style="color:#dc2626;font-size:10px;">{{ $message }}</span> @enderror
                        </div>
                    @endif
                @endforeach
            </div>
        @elseif ($station === 'lab')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px 16px;">
                @foreach ($C::LAB_FIELDS as $field => $label)
                    <div><label class="form-label">{{ $label }}</label><input type="text" wire:model="form.{{ $field }}" class="form-input"></div>
                @endforeach
                <div style="grid-column:1/-1;"><label class="form-label">Kesimpulan</label><textarea wire:model="form.kesimpulan" class="form-input" rows="2"></textarea></div>
            </div>
        @elseif ($station === 'radiology')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 16px;">
                @foreach ($C::RADIOLOGY_FIELDS as $field => $label)
                    <div><label class="form-label">{{ $label }}</label><input type="text" wire:model="form.{{ $field }}" class="form-input"></div>
                @endforeach
                <div style="grid-column:1/-1;"><label class="form-label">Kesimpulan</label><textarea wire:model="form.kesimpulan" class="form-input" rows="2"></textarea></div>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 16px;">
                @foreach ($C::DOCTOR_FIELDS as $field => $label)
                    <div><label class="form-label">{{ $label }}</label><input type="text" wire:model="form.{{ $field }}" class="form-input"></div>
                @endforeach
                <div style="grid-column:1/-1;"><label class="form-label">Diagnosa</label><textarea wire:model="form.diagnosa" class="form-input" rows="2"></textarea></div>
                <div style="grid-column:1/-1;"><label class="form-label">Rekomendasi</label><textarea wire:model="form.rekomendasi" class="form-input" rows="2"></textarea></div>
                <div style="grid-column:1/-1;"><label class="form-label">Catatan</label><textarea wire:model="form.catatan" class="form-input" rows="2"></textarea></div>
            </div>
        @endif

        @if ($this->hasUpload())
            <div style="margin-top:12px;"><label class="form-label">Lampiran (opsional)</label><input type="file" wire:model="upload" class="form-input"></div>
        @endif

        <button type="submit" wire:loading.attr="disabled" class="btn-primary" style="margin-top:14px;"><i class="fa-solid fa-floppy-disk"></i> Simpan {{ $this->stationLabel() }}</button>
    </form>
@endif
</div>
