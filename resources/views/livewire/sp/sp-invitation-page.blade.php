<div>
<x-page-header title="Undangan Klarifikasi" subtitle="Karyawan dan atasan langsung diundang sebelum Berita Acara" :back="route('sp')" />

@php $record = $this->record; @endphp

@if ($editing)
    <form wire:submit="save">
        <div class="form-card">
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Karyawan @if (! $record)<span style="color:var(--danger-text);">*</span>@endif</label>
                    @if ($record)
                        <p style="font-size:14px;font-weight:600;color:var(--text-heading);margin:0;">{{ $record->user->name }}</p>
                    @else
                        <select data-tom-select data-placeholder="Pilih karyawan" wire:model.live="user_id" class="form-input">
                            <option value="">Pilih karyawan</option>
                            @foreach ($this->employeeOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('user_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    @endif
                    @if ($record || $user_id)
                        <p style="font-size:12.5px;color:var(--text-muted);margin:6px 0 0;">
                            Atasan langsung yang ikut diundang:
                            @if ($this->supervisor)
                                <strong style="color:var(--text-heading);">{{ $this->supervisor->name }}</strong>
                            @else
                                tidak ditemukan.
                            @endif
                        </p>
                    @endif
                </div>

                <div>
                    <label class="form-label">Waktu <span style="color:var(--danger-text);">*</span></label>
                    <input type="datetime-local" wire:model="scheduled_at" class="form-input">
                    @error('scheduled_at') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Tempat <span style="color:var(--danger-text);">*</span></label>
                    <input type="text" wire:model="location" class="form-input" placeholder="mis. Ruang Meeting HC lantai 2">
                    @error('location') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Catatan</label>
                    <textarea wire:model="note" class="form-input" rows="3"></textarea>
                    @error('note') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;">
            @if ($record)
                <button type="button" wire:click="$set('editing', false)" class="btn-ghost">Batal</button>
            @endif
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> {{ $record ? 'Simpan Perubahan' : 'Kirim Undangan' }}</button>
        </div>
    </form>
@else
    @include('livewire.partials.invitation-detail', ['record' => $record, 'baLink' => true])

    @if ($this->isOpen)
        <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:12px;margin-top:20px;">
            <button type="button" wire:click="edit" class="btn-ghost"><i class="fa-solid fa-pen" style="font-size:12px;"></i> Ubah</button>
            <a href="{{ route('ba.create', ['invitation_id' => $record->id]) }}" wire:navigate class="btn-primary"><i class="fa-solid fa-file-lines" style="font-size:12px;"></i> Buat Berita Acara</a>
        </div>

        <div class="form-card" style="margin-top:20px;">
            <label class="form-label">Batalkan undangan</label>
            <textarea wire:model="cancel_reason" class="form-input" rows="2" placeholder="Alasan pembatalan"></textarea>
            @error('alasan') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            @error('status') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            <button type="button" wire:click="cancel" wire:confirm="Batalkan undangan ini?" class="btn-ghost" style="margin-top:10px;color:var(--danger-text);">Batalkan Undangan</button>
        </div>
    @endif
@endif
</div>
