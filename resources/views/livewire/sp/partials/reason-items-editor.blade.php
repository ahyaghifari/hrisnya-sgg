{{-- Poin pelanggaran dari master (Dasar Klarifikasi BA / poin SP), nomor otomatis + catatan tambahan.
     Butuh: $field (nama property array berisi ['reason' => ..., 'note' => ...]), $add & $remove (nama method),
     $addLabel, $this->reasonOptions (id => nama); opsional $repeatOptions (value => label, pelanggaran berulang dari SP sebelumnya). --}}
@php $rows = $$field; $repeatOptions = $repeatOptions ?? []; $options = $options ?? $this->reasonOptions; $noun = $noun ?? 'pelanggaran'; $allowOther = $allowOther ?? false; $lowerGroups = $lowerGroups ?? []; $grouped = $repeatOptions || $lowerGroups; @endphp
<div style="display:flex;flex-direction:column;gap:10px;">
    @foreach ($rows as $index => $row)
        <div wire:key="{{ $field }}-{{ $index }}" style="display:flex;gap:8px;align-items:flex-start;">
            <span style="min-width:20px;padding-top:10px;font-size:13px;color:var(--text-muted);">{{ $index + 1 }}.</span>
            <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
                <select wire:model.live="{{ $field }}.{{ $index }}.reason" class="form-input">
                    <option value="">Pilih {{ $noun }}</option>
                    @if ($repeatOptions)
                        <optgroup label="Pelanggaran sama dengan SP sebelumnya">
                            @foreach ($repeatOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    {{-- Group hanya perlu kalau ada opsi "berulang" / pelanggaran level bawah; tanpa itu daftar langsung. --}}
                    @if ($grouped)<optgroup label="{{ $noun === 'pelanggaran' ? 'Pelanggaran Surat Peringatan' : ucfirst($noun) . ' lainnya' }}">@endif
                        @foreach ($options as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    @if ($grouped)</optgroup>@endif
                    @foreach ($lowerGroups as $groupLabel => $groupOptions)
                        <optgroup label="{{ $groupLabel }}">
                            @foreach ($groupOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                    @if ($allowOther)<option value="other">Pelanggaran Lainnya</option>@endif
                </select>
                @if ($allowOther && ($row['reason'] ?? '') === 'other')
                    <input type="text" wire:model="{{ $field }}.{{ $index }}.note" class="form-input" placeholder="Tulis pelanggaran lainnya">
                @endif
                @error($field . '.' . $index . '.reason') <p style="color:var(--danger-text);font-size:12px;margin:0;">{{ $message }}</p> @enderror
            </div>
            @if (count($rows) > 1)
                <button type="button" wire:click="{{ $remove }}({{ $index }})" class="btn-ghost" style="padding:8px 10px;color:var(--danger-text);">
                    <i class="fa-solid fa-trash"></i>
                </button>
            @endif
        </div>
    @endforeach
</div>
<button type="button" wire:click="{{ $add }}" class="btn-ghost" style="margin-top:8px;font-size:12.5px;">
    <i class="fa-solid fa-plus"></i> {{ $addLabel }}
</button>
@error($field) <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
