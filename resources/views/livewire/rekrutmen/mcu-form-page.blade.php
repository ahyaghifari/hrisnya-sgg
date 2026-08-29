<div>
<x-page-header title="Input MCU Pelamar" :back="route('rekrutmen.pelamar.show', $jobApplicationId)" />

@php
    $C = \App\Livewire\Rekrutmen\McuFormPage::class;
@endphp

@if ($status !== 'mcu')
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:24px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Pelamar ini tidak sedang di tahap MCU (status: {{ $status ?: 'tidak diketahui' }}).</p>
    </div>
@else
    <p style="font-size:12px;color:var(--text-muted);margin:0 0 16px;">Keputusan MCU: <strong>{{ ['pending' => 'Belum diputuskan', 'fit' => 'FIT', 'unfit' => 'UNFIT'][$statusMcu] ?? $statusMcu }}</strong></p>

    {{-- ── Perawat ── --}}
    @if ($this->canNurse)
        <form wire:submit.prevent="simpanNurse" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Asesmen Perawat &amp; EKG {!! $filled['nurse'] ? '<span class="badge badge-approved" style="font-size:10px;">terisi</span>' : '' !!}</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px 16px;">
                @foreach ($C::NURSE_FIELDS as $field => [$type, $label])
                    @if ($type === 'bool')
                        <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;">
                            <input type="checkbox" wire:model="nurse.{{ $field }}"> {{ $label }}
                        </label>
                    @elseif ($type === 'textarea')
                        <div style="grid-column:1/-1;"><label class="form-label">{{ $label }}</label><textarea wire:model="nurse.{{ $field }}" class="form-input" rows="2"></textarea></div>
                    @else
                        <div><label class="form-label">{{ $label }}</label><input type="{{ $type === 'text' ? 'text' : 'number' }}" @if($type==='number') step="0.1" @endif wire:model="nurse.{{ $field }}" class="form-input">
                            @error('nurse.'.$field) <span style="color:#dc2626;font-size:10px;">{{ $message }}</span> @enderror
                        </div>
                    @endif
                @endforeach
                <div style="grid-column:1/-1;"><label class="form-label">Lampiran EKG (opsional)</label><input type="file" wire:model="nurseFile" class="form-input"></div>
            </div>
            <button type="submit" wire:loading.attr="disabled" class="btn-primary" style="margin-top:12px;"><i class="fa-solid fa-floppy-disk"></i> Simpan Asesmen Perawat</button>
        </form>
    @endif

    {{-- ── Laboratorium ── --}}
    @if ($this->canLab)
        <form wire:submit.prevent="simpanLab" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Hasil Laboratorium {!! $filled['lab'] ? '<span class="badge badge-approved" style="font-size:10px;">terisi</span>' : '' !!}</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px 16px;">
                @foreach ($C::LAB_FIELDS as $field => $label)
                    <div><label class="form-label">{{ $label }}</label><input type="text" wire:model="lab.{{ $field }}" class="form-input"></div>
                @endforeach
                <div style="grid-column:1/-1;"><label class="form-label">Kesimpulan</label><textarea wire:model="lab.kesimpulan" class="form-input" rows="2"></textarea></div>
                <div style="grid-column:1/-1;"><label class="form-label">Lampiran hasil (opsional)</label><input type="file" wire:model="labFile" class="form-input"></div>
            </div>
            <button type="submit" wire:loading.attr="disabled" class="btn-primary" style="margin-top:12px;"><i class="fa-solid fa-floppy-disk"></i> Simpan Hasil Lab</button>
        </form>
    @endif

    {{-- ── Radiologi ── --}}
    @if ($this->canRadiology)
        <form wire:submit.prevent="simpanRadiology" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Hasil Radiologi {!! $filled['radiology'] ? '<span class="badge badge-approved" style="font-size:10px;">terisi</span>' : '' !!}</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 16px;">
                @foreach ($C::RADIOLOGY_FIELDS as $field => $label)
                    <div><label class="form-label">{{ $label }}</label><input type="text" wire:model="radiology.{{ $field }}" class="form-input"></div>
                @endforeach
                <div style="grid-column:1/-1;"><label class="form-label">Kesimpulan</label><textarea wire:model="radiology.kesimpulan" class="form-input" rows="2"></textarea></div>
                <div style="grid-column:1/-1;"><label class="form-label">Lampiran foto (opsional)</label><input type="file" wire:model="radiologyFile" class="form-input"></div>
            </div>
            <button type="submit" wire:loading.attr="disabled" class="btn-primary" style="margin-top:12px;"><i class="fa-solid fa-floppy-disk"></i> Simpan Hasil Radiologi</button>
        </form>
    @endif

    {{-- ── Dokter ── --}}
    @if ($this->canDoctor)
        <form wire:submit.prevent="simpanDokter" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Pemeriksaan Dokter {!! $filled['doctor'] ? '<span class="badge badge-approved" style="font-size:10px;">terisi</span>' : '' !!}</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 16px;">
                @foreach ($C::DOCTOR_FIELDS as $field => $label)
                    <div><label class="form-label">{{ $label }}</label><input type="text" wire:model="doctor.{{ $field }}" class="form-input"></div>
                @endforeach
                <div style="grid-column:1/-1;"><label class="form-label">Diagnosa</label><textarea wire:model="doctor.diagnosa" class="form-input" rows="2"></textarea></div>
                <div style="grid-column:1/-1;"><label class="form-label">Rekomendasi</label><textarea wire:model="doctor.rekomendasi" class="form-input" rows="2"></textarea></div>
                <div style="grid-column:1/-1;"><label class="form-label">Catatan</label><textarea wire:model="doctor.catatan" class="form-input" rows="2"></textarea></div>
            </div>
            <button type="submit" wire:loading.attr="disabled" class="btn-primary" style="margin-top:12px;"><i class="fa-solid fa-floppy-disk"></i> Simpan Pemeriksaan Dokter</button>
        </form>
    @endif

    @if (! $this->canNurse && ! $this->canLab && ! $this->canRadiology && ! $this->canDoctor)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:24px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Anda tidak punya izin mengisi stasiun MCU manapun.</p>
        </div>
    @endif
@endif
</div>
