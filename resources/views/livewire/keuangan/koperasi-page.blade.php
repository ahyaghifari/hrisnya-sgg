<div>
    <x-page-header title="Koperasi" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{-- Cari/company lewat Livewire (server-side) — SEBELUMNYA pakai List.js di browser,
             tapi List.js mengunci baris tbody-nya sendiri sehingga tidak ikut ke-morph Livewire
             tiap company/data ganti (baris lama nyangkut terus), jadi dicabut. --}}
        <div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                    <x-company-filter :companies="$this->companies" :system="\App\Enum\CustomPermissionEnum::SISTEM_KEUANGAN" wire:model.live="companyId" />

                    <div style="position:relative;">
                        <label class="form-label">Cari</label>
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nama / NIP..." style="min-width:220px;">
                    </div>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export" class="btn-ghost" style="padding:9px 16px;font-size:12.5px;">
                        <i class="fa-solid fa-file-excel"></i> Export
                    </button>
                    @if ($this->canCreateKoperasi)
                        <button type="button" wire:click="openAddModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                            <i class="fa-solid fa-plus"></i> Tambah Anggota Koperasi
                        </button>
                    @endif
                </div>
            </div>

            @if (count($selected) > 0 && $this->canUpdateKoperasi)
                <div style="display:flex;align-items:center;gap:10px;background:var(--surface-soft,#f8fafc);border:1px solid var(--border);border-radius:10px;padding:8px 14px;margin-top:12px;">
                    <span style="font-size:12.5px;font-weight:600;">{{ count($selected) }} dipilih</span>
                    <button type="button" wire:click="openEditModal" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                        <i class="fa-solid fa-pen"></i> Ubah Amount
                    </button>
                </div>
            @endif

            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:16px;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;width:36px;">
                                <input type="checkbox" wire:click="toggleSelectAll($event.target.checked)">
                            </th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                            <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Potongan Per Bulan</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->members as $member)
                            <tr wire:key="'koperasi-'.{{ $member->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;">
                                    <input type="checkbox" wire:model="selected" value="{{ $member->id }}">
                                </td>
                                <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $member->user?->name ?? '-' }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $member->user?->nip ?? '-' }}</td>
                                <td style="padding:9px 14px;text-align:right;white-space:nowrap;">Rp {{ number_format($member->amount, 0, ',', '.') }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    @can('update', $member)
                                        <button type="button" wire:click="toggleActive('{{ $member->id }}')" wire:confirm="Ubah status keanggotaan koperasi ini?"
                                            style="border:none;background:none;cursor:pointer;font-weight:600;color:{{ $member->is_active ? '#047C54' : 'var(--text-faint)' }};">
                                            {{ $member->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    @else
                                        <span style="font-weight:600;color:{{ $member->is_active ? '#047C54' : 'var(--text-faint)' }};">
                                            {{ $member->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    @endcan
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;">
                                        @can('update', $member)
                                            <button type="button" wire:click="openEditModal('{{ $member->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </button>
                                        @endcan
                                        @can('delete', $member)
                                            <button type="button" wire:click="removeMember('{{ $member->id }}')" wire:confirm="Hapus anggota ini dari koperasi? Riwayat payroll sebelumnya tidak terpengaruh." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada anggota koperasi untuk perusahaan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal Tambah Anggota --}}
    @if ($showAddModal)
        <div class="modal-overlay" wire:click="closeAddModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Tambah Anggota Koperasi</h3>

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
                        <label class="form-label">Potongan Per Bulan</label>
                        <input type="number" step="0.01" min="0" class="form-input" wire:model="addAmount" placeholder="150000">
                        @error('addAmount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeAddModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveAdd" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Ubah Amount (satu atau beberapa baris yang dicentang) --}}
    @if ($showEditModal)
        <div class="modal-overlay" wire:click="closeEditModal">
            <div class="modal-box" style="max-width:380px;" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    Ubah Amount ({{ count($editingIds) }} anggota)
                </h3>

                <div>
                    <label class="form-label">Potongan Per Bulan</label>
                    <input type="number" step="0.01" min="0" class="form-input" wire:model="editAmount" placeholder="150000">
                    @error('editAmount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
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
