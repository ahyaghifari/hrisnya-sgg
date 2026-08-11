<div>
    <x-page-header title="Tunjangan & Potongan Eksekutif" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="background:rgba(37,99,235,0.08);border:1px solid rgba(37,99,235,0.3);border-radius:10px;padding:14px 16px;font-size:12.5px;color:#1d4ed8;">
            <i class="fa-solid fa-circle-info"></i>
            Khusus Presiden Direktur & Sekretaris Direktur — potongan biasa (Keterlambatan, Koperasi, dst) tidak berlaku
            untuk mereka, jadi baris pendapatan/potongan tambahan (mis. BPJS nominal khusus) diatur manual di sini.
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:flex-end;">
            @if ($this->canCreate)
                <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-plus"></i> Tambah Baris
                </button>
            @endif
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Penerima</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Jenis</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Label</th>
                        <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Nominal</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->allowances as $allowance)
                        <tr wire:key="'exec-allowance-'.{{ $allowance->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $allowance->user?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <span style="padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;{{ $allowance->type === 'earning' ? 'background:rgba(22,163,74,0.1);color:#16a34a;' : 'background:rgba(220,38,38,0.1);color:#dc2626;' }}">
                                    {{ $allowance->type === 'earning' ? 'Pendapatan' : 'Potongan' }}
                                </span>
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $allowance->label }}</td>
                            <td style="padding:9px 14px;text-align:right;white-space:nowrap;">Rp {{ number_format($allowance->amount, 0, ',', '.') }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span style="padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;{{ $allowance->is_active ? 'background:rgba(22,163,74,0.1);color:#16a34a;' : 'background:rgba(107,114,128,0.1);color:#6b7280;' }}">
                                    {{ $allowance->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    @can('update', $allowance)
                                        <button type="button" wire:click="toggleActive('{{ $allowance->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-power-off"></i> {{ $allowance->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                        <button type="button" wire:click="openEditModal('{{ $allowance->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                    @endcan
                                    @can('delete', $allowance)
                                        <button type="button" wire:click="removeAllowance('{{ $allowance->id }}')" wire:confirm="Hapus baris ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                            <i class="fa-solid fa-trash"></i> Hapus
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada baris tunjangan/potongan eksekutif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah/Ubah --}}
    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box" style="max-width:440px;" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    {{ $editingId ? 'Ubah Baris' : 'Tambah Baris' }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Penerima <span style="color:var(--danger-text);">*</span></label>
                        <select class="form-input" wire:model="formUserId">
                            <option value="">-- Pilih --</option>
                            @foreach ($this->employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        @error('formUserId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Jenis <span style="color:var(--danger-text);">*</span></label>
                        <select class="form-input" wire:model="formType">
                            <option value="earning">Pendapatan</option>
                            <option value="deduction">Potongan</option>
                        </select>
                        @error('formType') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Label <span style="color:var(--danger-text);">*</span></label>
                        <input type="text" class="form-input" wire:model="formLabel" placeholder="mis. BPJS Kesehatan (Nominal Khusus)">
                        @error('formLabel') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Nominal <span style="color:var(--danger-text);">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-input" wire:model="formAmount" placeholder="500000">
                        @error('formAmount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Catatan</label>
                        <textarea class="form-input" rows="3" wire:model="formNote"></textarea>
                        @error('formNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
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
