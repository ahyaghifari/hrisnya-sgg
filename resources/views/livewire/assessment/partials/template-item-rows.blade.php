@foreach ($items as $index => $item)
    <div style="border:1px solid var(--border-subtle);border-radius:12px;padding:14px;margin-bottom:12px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <span style="font-size:12px;font-weight:700;color:var(--text-muted);">Indikator #{{ $index + 1 }}</span>
            @if (count($items) > 1)
                <button type="button" wire:click="removeItem({{ $index }})" style="background:none;border:none;color:var(--danger-text);cursor:pointer;font-size:12px;"><i class="fa-solid fa-trash"></i></button>
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;">
            <div>
                <label class="form-label">Pertanyaan / Indikator <span style="color:var(--danger-text);">*</span></label>
                <input type="text" class="form-input" wire:model="items.{{ $index }}.name" placeholder="Contoh: Atasan memberi arahan dengan jelas">
                @error('items.' . $index . '.name') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
                <div>
                    <label class="form-label">Label Grup</label>
                    <input type="text" class="form-input" wire:model="items.{{ $index }}.group_label" placeholder="mis. Kedisiplinan">
                </div>
                <div>
                    <label class="form-label">Bobot (%)</label>
                    <input type="number" step="0.01" min="0" max="100" class="form-input" wire:model="items.{{ $index }}.weight_percent">
                    @error('items.' . $index . '.weight_percent') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Target Nilai</label>
                    <input type="number" step="0.01" min="0" class="form-input" wire:model="items.{{ $index }}.target_score">
                </div>
                <div>
                    <label class="form-label">Skor Maksimal</label>
                    <input type="number" step="1" min="0" class="form-input" wire:model="items.{{ $index }}.max_score">
                </div>
            </div>
        </div>
    </div>
@endforeach

<button type="button" wire:click="addItem" class="btn-ghost" style="width:100%;justify-content:center;"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Tambah Indikator</button>
