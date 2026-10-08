<div>
<x-page-header title="Buat Berita Acara" subtitle="Isi lengkap sekaligus, disimpan sebagai draft" :back="route('sp')" />

<form wire:submit="save">
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-id-card" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Informasi</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                @php $invitation = $this->invitation; @endphp
                <label class="form-label">Karyawan (dari undangan klarifikasi)</label>
                <p style="font-size:14px;font-weight:600;color:var(--text-heading);margin:0;">{{ $invitation->user->name }}</p>
                <p style="font-size:12.5px;color:var(--text-muted);margin:6px 0 0;">
                    Klarifikasi: {{ $invitation->scheduled_at->translatedFormat('d M Y H:i') }}, {{ $invitation->location }}.
                    Atasan langsung:
                    @if ($invitation->supervisor)
                        <strong style="color:var(--text-heading);">{{ $invitation->supervisor->name }}</strong>
                        {{ $invitation->supervisor->activeJobPosition?->jobPosition?->name ? '(' . $invitation->supervisor->activeJobPosition->jobPosition->name . ')' : '' }}
                        &middot; ikut menandatangani setelah yang mengetahui.
                    @else
                        tidak ditemukan.
                    @endif
                </p>
            </div>

            <div>
                <label class="form-label">Mengetahui <span style="color:var(--danger-text);">*</span></label>
                <select data-tom-select data-placeholder="Pilih yang mengetahui" wire:model="knows_by" class="form-input">
                    <option value="">Pilih yang mengetahui</option>
                    @foreach ($this->knowsByOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('knows_by') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            @if (\App\Services\Sp\SpReportService::coachingSourceEnabled())
            <div>
                <label class="form-label">Sumber dari Coaching (opsional)</label>
                <select wire:model="coaching_id" class="form-input">
                    <option value="">Tanpa Coaching</option>
                    @foreach ($this->coachingOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('coaching_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            @endif
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
            <span class="section-title">Dasar &amp; Isi</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Dasar Klarifikasi <span style="color:var(--danger-text);">*</span></label>
                @include('livewire.sp.partials.reason-items-editor', ['options' => $this->dasarReasonOptions, 'noun' => 'dasar klarifikasi', 'withNote' => false, 'field' => 'dasar_items', 'add' => 'addDasarItem', 'remove' => 'removeDasarItem', 'addLabel' => 'Tambah Dasar'])
            </div>

            @foreach ([['kejadian', 'Kejadian', true], ['subject_clarification', 'Klarifikasi Karyawan', false], ['analysis', 'Analisis', false], ['kesimpulan', 'Kesimpulan', false], ['followup_plan', 'Rencana Tindak Lanjut', false]] as [$field, $label, $required])
                <div>
                    <label class="form-label">{{ $label }} @if ($required)<span style="color:var(--danger-text);">*</span>@endif</label>
                    <div wire:ignore>
                        <input type="hidden" id="trix-ba-{{ $field }}" wire:model="{{ $field }}" value="{{ $$field }}">
                        <trix-editor input="trix-ba-{{ $field }}" style="min-height:100px;"></trix-editor>
                    </div>
                    @error($field) <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('sp') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan Draft</button>
    </div>
</form>
</div>
