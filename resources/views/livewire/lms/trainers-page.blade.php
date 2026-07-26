<div>
    <x-page-header title="Pemateri" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;width:fit-content;">
            <button type="button" wire:click="setStatus('aktif')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === 'aktif' ? 'var(--surface)' : 'transparent' }};color:{{ $status === 'aktif' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === 'aktif' ? 'var(--shadow-card)' : 'none' }};">Aktif</button>
            <button type="button" wire:click="setStatus('tidak_aktif')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === 'tidak_aktif' ? 'var(--surface)' : 'transparent' }};color:{{ $status === 'tidak_aktif' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === 'tidak_aktif' ? 'var(--shadow-card)' : 'none' }};">Tidak Aktif</button>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
            <div style="position:relative;">
                <label class="form-label">Cari</label>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nama pemateri..." style="min-width:200px;">
            </div>

            @if ($this->canCreateTrainer)
                <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-plus"></i> Tambah Pemateri
                </button>
            @endif
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tipe</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah Pelatihan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Keahlian</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Kontak</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->trainers as $trainer)
                        <tr wire:key="'trainer-'.{{ $trainer->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $trainer->name }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ ucfirst($trainer->type) }}</span>
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $trainer->trainings_count }}</td>
                            <td style="padding:9px 14px;">{{ $trainer->expertise ?: '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <div>{{ $trainer->email ?: '-' }}</div>
                                <div style="color:var(--text-muted);">{{ $trainer->phone ?: '-' }}</div>
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    <a href="{{ route('pelatihan.pemateri.show', $trainer) }}" wire:navigate class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                        <i class="fa-solid fa-eye"></i> Lihat
                                    </a>
                                    @can('update', $trainer)
                                        <button type="button" wire:click="openEditModal('{{ $trainer->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                    @endcan
                                    @if ($trainer->trainings_count > 0)
                                        @can('update', $trainer)
                                            <button type="button" wire:click="toggleActive('{{ $trainer->id }}')" wire:confirm="{{ $trainer->is_active ? 'Nonaktifkan' : 'Aktifkan' }} pemateri ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                <i class="fa-solid {{ $trainer->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i> {{ $trainer->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        @endcan
                                    @else
                                        @can('delete', $trainer)
                                            <button type="button" wire:click="removeTrainer('{{ $trainer->id }}')" wire:confirm="Hapus pemateri ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada pemateri untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $this->trainers->links('livewire.partials.pagination') }}
        </div>
    </div>

    {{-- Modal Tambah/Ubah Pemateri --}}
    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    {{ $editingId ? 'Ubah Pemateri' : 'Tambah Pemateri' }}
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2" style="gap:12px;">
                    <div>
                        <label class="form-label">Nama *</label>
                        <input type="text" class="form-input" wire:model="formName">
                        @error('formName') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Tipe *</label>
                        <select class="form-input" wire:model="formType">
                            <option value="external">External</option>
                            <option value="internal">Internal</option>
                        </select>
                        @error('formType') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="grid-column:span 2;">
                        <label class="form-label">Karyawan</label>
                        <select data-tom-select data-placeholder="-- Isi kalau pemateri ini karyawan sendiri --" class="form-input" wire:model="formUserId">
                            <option value="">-- Isi kalau pemateri ini karyawan sendiri --</option>
                            @foreach ($this->employeeOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('formUserId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Keahlian</label>
                        <input type="text" class="form-input" wire:model="formExpertise">
                        @error('formExpertise') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Perusahaan Asal</label>
                        <input type="text" class="form-input" wire:model="formCompany">
                        @error('formCompany') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" wire:model="formEmail">
                        @error('formEmail') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Telepon</label>
                        <input type="text" class="form-input" wire:model="formPhone">
                        @error('formPhone') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="grid-column:span 2;">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-input" rows="2" wire:model="formAddress"></textarea>
                        @error('formAddress') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
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
