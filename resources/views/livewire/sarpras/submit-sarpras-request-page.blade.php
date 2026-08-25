<div>
    <div class="page-hero" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Ajukan Sarana &amp; Prasarana</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Perbaikan atau pengadaan barang/fasilitas unit Anda</p>
        </div>
    </div>

    <form wire:submit="submit" style="display:flex;flex-direction:column;gap:16px;">
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
            <div>
                <label class="form-label">Unit / Divisi</label>
                @if ($unitDisplay)
                    <div class="form-input" style="background:var(--surface-alt);color:var(--text-muted);display:flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-sitemap"></i> {{ $unitDisplay }}
                    </div>
                @else
                    <div class="form-input" style="background:var(--danger-soft, #fee2e2);color:var(--danger-text);display:flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Belum terhubung ke Divisi manapun
                    </div>
                    <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">Hubungi HC untuk mengatur struktur organisasi Anda sebelum bisa mengajukan.</p>
                @endif
            </div>
            <div>
                <label class="form-label">Jenis</label>
                <select wire:model="jenis" class="form-input">
                    <option value="perbaikan">Perbaikan</option>
                    <option value="pengadaan">Pengadaan</option>
                </select>
                @error('jenis') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Prioritas</label>
                <select wire:model="prioritas" class="form-input">
                    <option value="urgent">Urgent</option>
                    <option value="normal">Normal</option>
                    <option value="rendah">Rendah</option>
                </select>
                @error('prioritas') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            <div style="grid-column:1/-1;">
                <label class="form-label">Perihal</label>
                <input type="text" wire:model="perihal" class="form-input" placeholder="Ringkasan singkat permohonan">
                @error('perihal') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            <div style="grid-column:1/-1;">
                <label class="form-label">Uraian</label>
                <textarea wire:model="uraian" class="form-input" rows="3" placeholder="Jelaskan kebutuhan/kerusakan secara rinci"></textarea>
                @error('uraian') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;">
            <h2 style="font-size:14px;font-weight:700;margin:0 0 12px;">Rincian Item</h2>

            @foreach ($items as $index => $item)
                <div wire:key="item-{{ $index }}" style="border:1.5px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;position:relative;">
                    @if (count($items) > 1)
                        <button type="button" wire:click="removeItem({{ $index }})" style="position:absolute;top:10px;right:10px;background:transparent;border:none;color:var(--text-faint);cursor:pointer;">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    @endif
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
                        <div style="grid-column:1/-1;">
                            <label class="form-label">Nama Barang</label>
                            <input type="text" wire:model="items.{{ $index }}.nama" class="form-input">
                            @error("items.{$index}.nama") <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                        <div style="grid-column:1/-1;">
                            <label class="form-label">Spesifikasi</label>
                            <input type="text" wire:model="items.{{ $index }}.spesifikasi" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Jumlah</label>
                            <input type="number" min="1" wire:model="items.{{ $index }}.jumlah" class="form-input">
                            @error("items.{$index}.jumlah") <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Satuan</label>
                            <input type="text" wire:model="items.{{ $index }}.satuan" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Estimasi Harga Satuan</label>
                            <input type="number" step="0.01" min="0" wire:model="items.{{ $index }}.harga_satuan" class="form-input">
                        </div>
                    </div>
                </div>
            @endforeach

            <button type="button" wire:click="addItem" class="btn-add">
                <i class="fa-solid fa-plus"></i> Tambah Item
            </button>
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;">
            <h2 style="font-size:14px;font-weight:700;margin:0 0 12px;">Lampiran (opsional)</h2>
            <input type="file" wire:model="attachments" multiple class="form-input">
            <p style="font-size:11px;color:var(--text-faint);margin:6px 0 0;">PDF, JPG, PNG, Word, Excel. Maks 5MB/berkas, maks 10 berkas.</p>
            @error('attachments') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            @error('attachments.*') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            <div wire:loading wire:target="attachments" style="font-size:12px;color:var(--text-muted);margin-top:6px;">Mengunggah...</div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <a href="{{ route('sarana-prasarana') }}" wire:navigate class="btn-ghost">Batal</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit" @disabled(! $unitDisplay)>
                <span wire:loading.remove wire:target="submit">Ajukan Permohonan</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </button>
        </div>
    </form>
</div>
