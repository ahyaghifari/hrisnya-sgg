<div>
    <x-page-header title="Data Rekening" subtitle="Kelola nama bank, no. rekening, dan atas nama rekening karyawan" />

    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
        @if ($canBrowseAllCompanies)
            <div style="min-width:220px;">
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="companyId" class="form-input">
                    <option value="">Semua perusahaan</option>
                    @foreach ($this->companyOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div style="min-width:220px;flex:1;">
            <label class="form-label">Cari</label>
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama, NIP..." style="padding-left:38px;">
            </div>
        </div>
    </div>

    @php $employees = $this->employees; @endphp

    {{-- MOBILE: card manual --}}
    <div class="md:hidden">
        @if ($employees->isEmpty())
            <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada karyawan yang cocok.</p>
            </div>
        @else
            <div class="pwa-list">
                @foreach ($employees as $employee)
                    <div class="pwa-list-row" style="align-items:center;">
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $employee->nama_lengkap }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $employee->nip ?: 'NIP belum diisi' }}</p>
                            <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">
                                {{ $employee->identity?->bank_name ?: 'Bank belum diisi' }}
                                @if ($employee->identity?->bank_account_number)
                                    &middot; {{ $employee->identity->bank_account_number }}
                                @endif
                            </p>
                        </div>
                        @if ($this->canEdit)
                            <button type="button" wire:click="openEditModal('{{ $employee->id }}')" class="btn-ghost" style="padding:6px 10px;" title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            <div style="margin-top:16px;">
                {{ $employees->links('livewire.partials.pagination') }}
            </div>
        @endif
    </div>

    {{-- DESKTOP: tabel --}}
    <div class="hidden md:block">
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $employees->isEmpty() ? 'display:none;' : '' }}">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Bank</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">No. Rekening</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Atas Nama Rekening</th>
                        @if ($this->canEdit)
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $employee)
                        <tr wire:key="'rekening-'.{{ $employee->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $employee->nama_lengkap }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $employee->nip ?: '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $employee->identity?->bank_name ?: '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $employee->identity?->bank_account_number ?: '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $employee->identity?->bank_account_name ?: '-' }}</td>
                            @if ($this->canEdit)
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <button type="button" wire:click="openEditModal('{{ $employee->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($employees->isEmpty())
            <div style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada karyawan yang cocok.</p>
            </div>
        @endif

        <div style="margin-top:16px;">
            {{ $employees->links('livewire.partials.pagination') }}
        </div>
    </div>

    {{-- Modal Edit Rekening --}}
    @if ($editingEmployeeId)
        <div class="modal-overlay" wire:click="closeEditModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Edit Data Rekening</h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Nama Bank</label>
                        <input type="text" class="form-input" wire:model="editBankName" placeholder="BRI, BCA, Mandiri, ...">
                        @error('editBankName') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">No. Rekening</label>
                        <input type="text" class="form-input" wire:model="editBankAccountNumber" placeholder="1234567890">
                        @error('editBankAccountNumber') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Atas Nama Rekening</label>
                        <input type="text" class="form-input" wire:model="editBankAccountName" placeholder="Nama sesuai buku rekening">
                        @error('editBankAccountName') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeEditModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="save" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
