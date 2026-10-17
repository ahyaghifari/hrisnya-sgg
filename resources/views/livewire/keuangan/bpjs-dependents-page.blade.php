<div>
    <x-page-header title="Tanggungan BPJS" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                    <x-company-filter :companies="$this->companies" wire:model.live="companyId" />

                    <div style="position:relative;">
                        <label class="form-label">Cari</label>
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nama / NIP..." style="min-width:220px;">
                    </div>
                </div>

                @if ($this->canCreate)
                    <button type="button" wire:click="openAddModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                        <i class="fa-solid fa-plus"></i> Tambah Tanggungan
                    </button>
                @endif
            </div>

            <p style="font-size:10.5px;color:var(--text-faint);margin-top:8px;">
                Jumlah tanggungan menambah pengali potongan BPJS Kesehatan (1 + jumlah tanggungan). Tidak berlaku untuk BPJS Ketenagakerjaan.
            </p>

            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:16px;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah Tanggungan</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Keterangan</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->dependents as $dependent)
                            <tr wire:key="'bpjs-dependent-'.{{ $dependent->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $dependent->employee?->nama_lengkap ?? '-' }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $dependent->employee?->nip ?? '-' }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $dependent->count }}</td>
                                <td style="padding:9px 14px;">{{ $dependent->note ?? '-' }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    @can('update', $dependent)
                                        <button type="button" wire:click="toggleActive('{{ $dependent->id }}')" wire:confirm="Ubah status tanggungan ini?"
                                            style="border:none;background:none;cursor:pointer;font-weight:600;color:{{ $dependent->is_active ? '#047C54' : 'var(--text-faint)' }};">
                                            {{ $dependent->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    @else
                                        <span style="font-weight:600;color:{{ $dependent->is_active ? '#047C54' : 'var(--text-faint)' }};">
                                            {{ $dependent->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    @endcan
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;">
                                        @can('update', $dependent)
                                            <button type="button" wire:click="openEditModal('{{ $dependent->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </button>
                                        @endcan
                                        @can('delete', $dependent)
                                            <button type="button" wire:click="removeDependent('{{ $dependent->id }}')" wire:confirm="Hapus data tanggungan ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada data tanggungan untuk perusahaan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal Tambah --}}
    @if ($showAddModal)
        <div class="modal-overlay" wire:click="closeAddModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Tambah Tanggungan BPJS</h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Karyawan</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="-- Pilih Karyawan --" wire:model="addEmployeeId">
                                <option value="">-- Pilih Karyawan --</option>
                                @foreach ($this->availableEmployees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->nama_lengkap }} ({{ $employee->nip }})</option>
                                @endforeach
                            </select>
                        </div>
                        @error('addEmployeeId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Jumlah Tanggungan</label>
                        <input type="number" step="1" min="1" class="form-input" wire:model="addCount" placeholder="2">
                        @error('addCount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Keterangan</label>
                        <input type="text" class="form-input" wire:model="addNote" placeholder="1 ibu, 1 adik">
                        @error('addNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeAddModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveAdd" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Edit --}}
    @if ($showEditModal)
        <div class="modal-overlay" wire:click="closeEditModal">
            <div class="modal-box" style="max-width:380px;" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Ubah Tanggungan BPJS</h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Jumlah Tanggungan</label>
                        <input type="number" step="1" min="1" class="form-input" wire:model="editCount">
                        @error('editCount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Keterangan</label>
                        <input type="text" class="form-input" wire:model="editNote" placeholder="1 ibu, 1 adik">
                        @error('editNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <p style="font-size:10.5px;color:var(--text-faint);margin-top:10px;">Baru masuk ke payroll setelah Generate/Regenerate dijalankan untuk periode yang dipilih.</p>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeEditModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveEdit" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
