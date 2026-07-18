<div class="p-4 md:p-6">
    <x-page-header title="Insentif" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{-- List.js (headless, resources/js/app.js) urus cari+filter status+pagination di
             browser, pola sama PayrollPeriodsPage/KoperasiPage. Company tetap lewat Livewire. --}}
        <div id="incentives-list" data-list-table data-list-value-names="penerima,program" wire:ignore.self>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                    <x-company-filter :companies="$this->companies" wire:model.live="companyId" />

                    <div style="position:relative;">
                        <label class="form-label">Cari</label>
                        <input type="search" class="form-input search" placeholder="Penerima / Nama Program..." style="min-width:220px;">
                    </div>
                </div>

                @if ($this->canCreate)
                    <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                        <i class="fa-solid fa-plus"></i> Tambah Insentif
                    </button>
                @endif
            </div>

            <div class="pelatihan-filter-nav" style="margin-top:12px;">
                <button type="button" data-list-filter-key="status" data-list-filter-value="" class="pelatihan-filter-btn navy is-active">Semua Status</button>
                <button type="button" data-list-filter-key="status" data-list-filter-value="belum" class="pelatihan-filter-btn rust">Belum Diproses</button>
                <button type="button" data-list-filter-key="status" data-list-filter-value="sudah" class="pelatihan-filter-btn green">Sudah Diproses</button>
            </div>

            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:16px;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                            <th class="sort" data-sort="penerima" style="text-align:left;padding:10px 14px;white-space:nowrap;cursor:pointer;">Penerima <i class="fa-solid fa-sort" style="font-size:9px;color:var(--text-faint);"></i></th>
                            <th class="sort" data-sort="program" style="text-align:left;padding:10px 14px;white-space:nowrap;cursor:pointer;">Nama Program <i class="fa-solid fa-sort" style="font-size:9px;color:var(--text-faint);"></i></th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tanggal</th>
                            <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Nominal</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="list">
                        @forelse ($this->incentives as $incentive)
                            <tr style="border-bottom:1px solid var(--border-soft);" data-filter-status="{{ $incentive->is_processed ? 'sudah' : 'belum' }}">
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $incentive->user?->company?->name ?? '-' }}</td>
                                <td class="penerima" style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $incentive->user?->name ?? '-' }}</td>
                                <td class="program" style="padding:9px 14px;white-space:nowrap;">{{ $incentive->name }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $incentive->incentive_date?->format('d M Y') }}</td>
                                <td style="padding:9px 14px;text-align:right;white-space:nowrap;">Rp {{ number_format($incentive->amount, 0, ',', '.') }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <span style="font-weight:600;color:{{ $incentive->is_processed ? '#047C54' : '#d97706' }};">
                                        {{ $incentive->is_processed ? 'Sudah Diproses' : 'Belum Diproses' }}
                                    </span>
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    @if (! $incentive->is_processed)
                                        <div style="display:inline-flex;gap:6px;">
                                            @can('update', $incentive)
                                                <button type="button" wire:click="openEditModal('{{ $incentive->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                    <i class="fa-solid fa-pen"></i> Edit
                                                </button>
                                            @endcan
                                            @can('delete', $incentive)
                                                <button type="button" wire:click="removeIncentive('{{ $incentive->id }}')" wire:confirm="Hapus insentif ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                    <i class="fa-solid fa-trash"></i> Hapus
                                                </button>
                                            @endcan
                                        </div>
                                    @else
                                        <span style="color:var(--text-faint);">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada insentif untuk perusahaan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal Tambah/Ubah Insentif --}}
    @if ($showFormModal)
        <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeFormModal">
            <div style="background:var(--surface);border-radius:14px;padding:20px;max-width:440px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    {{ $editingId ? 'Ubah Insentif' : 'Tambah Insentif' }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Penerima</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="-- Pilih Karyawan --" wire:model="formUserId">
                                <option value="">-- Pilih Karyawan --</option>
                                @foreach ($this->employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->nip }})</option>
                                @endforeach
                            </select>
                        </div>
                        @error('formUserId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Nama Program</label>
                        <input type="text" class="form-input" wire:model="formName">
                        @error('formName') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-input" wire:model="formDate">
                        @error('formDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Nominal</label>
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
