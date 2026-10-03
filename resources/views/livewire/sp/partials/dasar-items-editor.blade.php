{{-- Dasar Klarifikasi per item, nomor otomatis. Butuh: $idPrefix (id Trix unik per halaman) + method addDasarItem/removeDasarItem + public array $dasar_items. --}}
<div style="display:flex;flex-direction:column;gap:8px;">
    @foreach ($dasar_items as $index => $item)
        <div wire:key="{{ $idPrefix }}-{{ $index }}" style="display:flex;gap:8px;align-items:flex-start;">
            <span style="min-width:20px;padding-top:10px;font-size:13px;color:var(--text-muted);">{{ $index + 1 }}.</span>
            <div wire:ignore style="flex:1;">
                <input type="hidden" id="{{ $idPrefix }}-{{ $index }}" wire:model="dasar_items.{{ $index }}" value="{{ $item }}">
                <trix-editor input="{{ $idPrefix }}-{{ $index }}" style="min-height:70px;"></trix-editor>
            </div>
            @if (count($dasar_items) > 1)
                <button type="button" wire:click="removeDasarItem({{ $index }})" class="btn-ghost" style="padding:8px 10px;color:var(--danger-text);">
                    <i class="fa-solid fa-trash"></i>
                </button>
            @endif
        </div>
        @error('dasar_items.' . $index) <p style="color:var(--danger-text);font-size:12px;margin-left:28px;">{{ $message }}</p> @enderror
    @endforeach
</div>
<button type="button" wire:click="addDasarItem" class="btn-ghost" style="margin-top:8px;font-size:12.5px;">
    <i class="fa-solid fa-plus"></i> Tambah Dasar
</button>
@error('dasar_items') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
