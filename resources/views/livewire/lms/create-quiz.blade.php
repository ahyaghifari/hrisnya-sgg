<div>
    <x-page-header
        title="{{ $editingQuizId ? 'Ubah Kuis' : 'Buat Kuis' }}"
        subtitle="{{ $editingQuizId ? 'Ubah judul kuis' : 'Soal ditambahkan setelah kuis dibuat' }}"
        :back="route('pelatihan.kuis')"
    />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Kategori *</label>
                    <div wire:ignore>
                        <select wire:model="categoryId" data-tom-select data-placeholder="Pilih kategori" class="form-input" required @disabled($editingQuizId)>
                            <option value="">Pilih kategori</option>
                            @foreach ($this->categoryOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($editingQuizId)
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kategori tidak bisa diubah setelah kuis dibuat.</p>
                    @endif
                    @error('categoryId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Judul Kuis *</label>
                    <input type="text" wire:model="title" class="form-input" placeholder="Judul kuis...">
                    @error('title') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('pelatihan.kuis') }}" wire:navigate class="btn-ghost" style="padding:10px 18px;font-size:12.5px;">Batal</a>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="padding:10px 22px;font-size:12.5px;">
                <span wire:loading.remove wire:target="save"><i class="fa-solid fa-save"></i> {{ $editingQuizId ? 'Simpan Perubahan' : 'Simpan & Pilih Soal' }}</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </div>
</div>
