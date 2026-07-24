<div>
    <x-page-header title="Kategori Pelatihan" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
            <div style="position:relative;">
                <label class="form-label">Cari</label>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nama kategori..." style="min-width:200px;">
            </div>

            @if ($this->canCreateCategory)
                <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-plus"></i> Tambah Kategori
                </button>
            @endif
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Kategori</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Jenis Pelatihan</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah Pelatihan</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->categories as $category)
                        <tr wire:key="'category-'.{{ $category->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $category->name }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                @if ($category->training_category)
                                    <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ $category->training_category->label() }}</span>
                                @else
                                    <span style="color:var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $category->courses_count }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    @can('update', $category)
                                        <button type="button" wire:click="openEditModal('{{ $category->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                    @endcan
                                    @can('delete', $category)
                                        @if ($category->courses_count === 0)
                                            <button type="button" wire:click="removeCategory('{{ $category->id }}')" wire:confirm="Hapus kategori ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada kategori untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah/Ubah Kategori --}}
    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    {{ $editingId ? 'Ubah Kategori' : 'Tambah Kategori' }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" class="form-input" wire:model="formName" placeholder="mis. Pelayanan Prima">
                        @error('formName') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Jenis Pelatihan</label>
                        <select class="form-input" wire:model="formTrainingCategory">
                            <option value="">-</option>
                            @foreach ($this->trainingCategoryOptions as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('formTrainingCategory') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeFormModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveForm" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
