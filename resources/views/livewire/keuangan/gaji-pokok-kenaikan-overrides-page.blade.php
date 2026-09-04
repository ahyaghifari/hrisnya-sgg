<div>
    <x-page-header title="Kenaikan Gaji Pokok" subtitle="Master persen kenaikan Gaji Pokok per karyawan, di luar setting default company" />

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

                @if ($this->canEdit)
                    <button type="button" wire:click="openAddModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                        <i class="fa-solid fa-plus"></i> Tambah Kenaikan
                    </button>
                @endif
            </div>

            <p style="font-size:11.5px;color:var(--text-faint);margin-top:10px;">
                Urutan prioritas persen kenaikan Gaji Pokok: <strong>Gaji Khusus</strong> (kalau ada, komponen Gaji Pokok tidak dihitung sama sekali) &rarr; baris AKTIF di sini &rarr; persen default company (halaman Komponen Gaji). Tidak berlaku untuk Orientasi.
            </p>

            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:16px;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                            <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Kenaikan</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Catatan</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->overrides as $override)
                            <tr wire:key="'override-'.{{ $override->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $override->user?->name ?? '-' }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $override->user?->nip ?? '-' }}</td>
                                <td style="padding:9px 14px;text-align:right;white-space:nowrap;">{{ rtrim(rtrim(number_format($override->rate, 2), '0'), '.') }}%</td>
                                <td style="padding:9px 14px;">{{ $override->note ?: '-' }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    @if ($this->canEdit)
                                        <button type="button" wire:click="toggleActive('{{ $override->id }}')" wire:confirm="Ubah status Kenaikan Gaji Pokok ini?"
                                            style="border:none;background:none;cursor:pointer;font-weight:600;color:{{ $override->is_active ? '#047C54' : 'var(--text-faint)' }};">
                                            {{ $override->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    @else
                                        <span style="font-weight:600;color:{{ $override->is_active ? '#047C54' : 'var(--text-faint)' }};">
                                            {{ $override->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    @endif
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    @if ($this->canEdit)
                                        <div style="display:inline-flex;gap:6px;">
                                            <button type="button" wire:click="openEditModal('{{ $override->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </button>
                                            <button type="button" wire:click="removeOverride('{{ $override->id }}')" wire:confirm="Hapus Kenaikan Gaji Pokok ini? Karyawan ini akan ikut persen default company lagi mulai Generate/Regenerate berikutnya." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada Kenaikan Gaji Pokok khusus untuk perusahaan ini.</td>
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
            <div class="modal-box" style="max-width:380px;" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Tambah Kenaikan Gaji Pokok</h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Karyawan</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="-- Pilih Karyawan --" wire:model="addUserId">
                                <option value="">-- Pilih Karyawan --</option>
                                @foreach ($this->availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->nip }})</option>
                                @endforeach
                            </select>
                        </div>
                        @error('addUserId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Persen Kenaikan (%)</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-input" wire:model="addRate" placeholder="16">
                        @error('addRate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Catatan (opsional)</label>
                        <input type="text" class="form-input" wire:model="addNote" placeholder="Alasan kenaikan berbeda...">
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
    @if ($editingId)
        <div class="modal-overlay" wire:click="closeEditModal">
            <div class="modal-box" style="max-width:380px;" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Ubah Kenaikan Gaji Pokok</h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Persen Kenaikan (%)</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-input" wire:model="editRate" placeholder="16">
                        @error('editRate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Catatan (opsional)</label>
                        <input type="text" class="form-input" wire:model="editNote" placeholder="Alasan kenaikan berbeda...">
                    </div>
                </div>

                <p style="font-size:10.5px;color:var(--text-faint);margin-top:10px;">Baru berlaku setelah Generate/Regenerate dijalankan untuk periode yang dipilih.</p>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeEditModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveEdit" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
