<div>
<style>
    .sp-level-group { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .sp-level-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 16px 12px; border-radius: 12px; border: 1.5px solid var(--border); background: var(--surface); color: var(--text-heading); font-family: inherit; font-size: 13.5px; font-weight: 700; text-align: center; cursor: pointer; transition: background 0.12s ease, border-color 0.12s ease; }
    .sp-level-btn i { font-size: 22px; }
    .sp-level-btn:hover { background: var(--surface-alt); }
    .sp-level-btn.is-active[data-level="sp1"] { background: linear-gradient(130deg, #eab308 0%, #a16207 100%); border-color: #a16207; color: #fff; }
    .sp-level-btn.is-active[data-level="sp2"] { background: linear-gradient(130deg, #f97316 0%, #c2410c 100%); border-color: #c2410c; color: #fff; }
    .sp-level-btn.is-active[data-level="sp3"] { background: linear-gradient(130deg, #ef4444 0%, #b91c1c 100%); border-color: #b91c1c; color: #fff; }
    @media (max-width: 767.98px) {
        .sp-level-group { gap: 8px; }
        .sp-level-btn { gap: 5px; padding: 11px 8px; font-size: 12px; border-radius: 10px; }
        .sp-level-btn i { font-size: 16px; }
    }
</style>

<form wire:submit="save">
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-triangle-exclamation" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Surat Peringatan</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Nomor Surat <span style="color:var(--danger-text);">*</span></label>
                <input type="text" wire:model="nomor" class="form-input" placeholder="mis. 001/SP/HC-GA/IX/2026">
                @error('nomor') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Level SP <span style="color:var(--danger-text);">*</span></label>
                <div class="sp-level-group" role="radiogroup" aria-label="Level SP">
                    @foreach (app(\App\Services\Sp\SpService::class)->allowedLevels($this->report->user_id) as $level)
                        <button type="button" role="radio" aria-checked="{{ $sp_level === $level->value ? 'true' : 'false' }}"
                            data-level="{{ $level->value }}"
                            wire:click="$set('sp_level', '{{ $level->value }}')"
                            class="sp-level-btn {{ $sp_level === $level->value ? 'is-active' : '' }}">
                            <i class="fa-solid {{ $level->icon() }}"></i>
                            <span>{{ $level->getLabel() }}</span>
                        </button>
                    @endforeach
                </div>
                @error('sp_level') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Pelanggaran <span style="color:var(--danger-text);">*</span></label>
                @include('livewire.sp.partials.reason-items-editor', ['allowOther' => true, 'lowerGroups' => $this->lowerReasonGroups, 'field' => 'items', 'add' => 'addItem', 'remove' => 'removeItem', 'addLabel' => 'Tambah Pelanggaran', 'repeatOptions' => $this->repeatOptions])
            </div>

            <div>
                <label class="form-label">Tanggal Mulai <span style="color:var(--danger-text);">*</span></label>
                <input type="date" wire:model.live="start_date" class="form-input">
                @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                @if ($this->endDatePreview)
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        <i class="fa-solid fa-circle-info"></i> Berlaku sampai: {{ \Illuminate\Support\Carbon::parse($this->endDatePreview)->translatedFormat('d F Y') }}
                    </p>
                @endif
            </div>

            <div>
                <label class="form-label">Mengetahui <span style="color:var(--danger-text);">*</span></label>
                <select data-tom-select data-placeholder="Pilih pimpinan/HC Supervisor/Manager" wire:model="knows_by" class="form-input">
                    <option value="">Pilih</option>
                    @foreach ($this->knowsByOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('knows_by') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;margin-top:20px;">
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan Draft</button>
    </div>
</form>
</div>
