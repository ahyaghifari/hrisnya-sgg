<div class="p-4 md:p-6">
    <x-page-header title="Insentif" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <x-company-filter :companies="$this->companies" wire:model.live="companyId" />
        </div>

        @if (! $this->insentifActive)
            <div style="background:rgba(217,119,6,0.08);border:1px solid rgba(217,119,6,0.3);border-radius:10px;padding:14px 16px;font-size:12.5px;color:#92400e;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Perusahaan ini belum mengaktifkan komponen gaji <strong>Insentif</strong>. Aktifkan dulu di halaman
                <strong>Komponen Gaji</strong> sebelum bisa mencatat insentif di sini.
            </div>
        @elseif ($this->insentifMetode !== 'master')
            <div style="background:rgba(217,119,6,0.08);border:1px solid rgba(217,119,6,0.3);border-radius:10px;padding:14px 16px;font-size:12.5px;color:#92400e;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Perusahaan ini memakai metode Insentif <strong>"Import"</strong> (bukan "Master"), jadi halaman ini tidak berlaku
                untuknya. Insentif untuk perusahaan ini diisi lewat upload Excel di halaman <strong>Payroll &gt; Import Insentif</strong>.
                Ubah setting "Metode Insentif" di halaman Komponen Gaji kalau memang mau pakai input manual di sini.
            </div>
        @else
            {{-- Cari/status/company lewat Livewire (server-side) — SEBELUMNYA pakai List.js di
                 browser, tapi List.js mengunci baris tbody-nya sendiri sehingga tidak ikut
                 ke-morph Livewire tiap company ganti (baris lama nyangkut terus), jadi dicabut. --}}
            <div>
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
                    <div style="position:relative;">
                        <label class="form-label">Cari</label>
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Penerima / Nama Program..." style="min-width:220px;">
                    </div>

                    @if ($this->canCreate)
                        <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                            <i class="fa-solid fa-plus"></i> Tambah Insentif
                        </button>
                    @endif
                </div>

                <div class="pelatihan-filter-nav" style="margin-top:12px;">
                    <button type="button" wire:click="setStatusFilter('')" class="pelatihan-filter-btn navy {{ $statusFilter === '' ? 'is-active' : '' }}">Semua Status</button>
                    <button type="button" wire:click="setStatusFilter('belum')" class="pelatihan-filter-btn rust {{ $statusFilter === 'belum' ? 'is-active' : '' }}">Belum Diproses</button>
                    <button type="button" wire:click="setStatusFilter('sudah')" class="pelatihan-filter-btn green {{ $statusFilter === 'sudah' ? 'is-active' : '' }}">Sudah Diproses</button>
                </div>

                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:16px;">
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                        <thead>
                            <tr style="border-bottom:1.5px solid var(--border);">
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Penerima</th>
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Program</th>
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tanggal</th>
                                <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Nominal</th>
                                <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                                <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->incentives as $incentive)
                                <tr wire:key="'incentive-'.{{ $incentive->id }}" style="border-bottom:1px solid var(--border-soft);">
                                    <td style="padding:9px 14px;white-space:nowrap;">{{ $incentive->user?->company?->name ?? '-' }}</td>
                                    <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $incentive->user?->name ?? '-' }}</td>
                                    <td style="padding:9px 14px;white-space:nowrap;">{{ $incentive->name }}</td>
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
        @endif
    </div>

    {{-- Modal Tambah/Ubah Insentif --}}
    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box" style="max-width:440px;" onclick="event.stopPropagation()">
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
