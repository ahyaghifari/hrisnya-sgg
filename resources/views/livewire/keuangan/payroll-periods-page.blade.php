<div >
    <x-page-header title="Periode Gaji" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{-- Company lewat Livewire (server-side) — SEBELUMNYA List.js dipasang buat
             sort+pagination di browser, tapi List.js mengunci baris tbody-nya sendiri sehingga
             tidak ikut ke-morph Livewire tiap company ganti (baris lama nyangkut terus), jadi
             dicabut. Tidak perlu kotak cari di sini, filter perusahaan sudah cukup. --}}
        <div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
                <x-company-filter :companies="$this->companies" wire:model.live="companyId" />

                @if ($this->canCreatePeriod)
                    <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                        <i class="fa-solid fa-plus"></i> Tambah Periode Gaji
                    </button>
                @endif
            </div>

            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:16px;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Dari Tanggal</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Sampai Tanggal</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Status Generate</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->periods as $period)
                            <tr wire:key="'period-'.{{ $period->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $period->company?->name ?? '-' }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $period->start_date->format('d M Y') }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $period->end_date->format('d M Y') }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">
                                    <span style="font-weight:600;color:{{ $period->processed_at ? '#047C54' : 'var(--text-faint)' }};">
                                        {{ $period->processed_at ? $period->processed_at->translatedFormat('d M Y H:i') : 'Belum digenerate' }}
                                    </span>
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;">
                                        @if (! $period->processed_at)
                                            @can('update', $period)
                                                <button type="button" wire:click="openEditModal('{{ $period->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                    <i class="fa-solid fa-pen"></i> Edit
                                                </button>
                                            @endcan
                                            @if ($this->canGeneratePayroll)
                                                <button type="button" wire:click="generatePayroll('{{ $period->id }}')" wire:confirm="Generate payroll untuk semua karyawan aktif di perusahaan ini? Aksi ini tidak bisa dibatalkan." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#047C54;">
                                                    <i class="fa-solid fa-cog"></i> Generate Payroll
                                                </button>
                                            @endif
                                        @else
                                            @if ($this->canGeneratePayroll)
                                                <button type="button" wire:click="regeneratePayroll('{{ $period->id }}')" wire:confirm="Hapus dan generate ulang seluruh payroll yang BELUM dibayar di periode ini? Payroll yang sudah dibayar tidak akan terpengaruh." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#d97706;">
                                                    <i class="fa-solid fa-arrow-rotate-right"></i> Regenerate Payroll
                                                </button>
                                            @endif
                                        @endif

                                        {{-- Penandatangan & Tanggal Bayar murni informasional (tidak dipakai perhitungan),
                                        jadi selalu bisa diubah kapan saja - tidak ikut terkunci begitu periode digenerate. --}}
                                        @can('update', $period)
                                            <button type="button" wire:click="openSignerModal('{{ $period->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                <i class="fa-solid fa-signature"></i> Penandatangan
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada periode gaji untuk perusahaan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    {{ $editingId ? 'Ubah Periode Gaji' : 'Tambah Periode Gaji' }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Perusahaan</label>
                        <select class="form-input" wire:model="formCompanyId">
                            @foreach ($this->companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('formCompanyId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-input" wire:model="formStartDate">
                        @error('formStartDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-input" wire:model="formEndDate">
                        @error('formEndDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Tanggal Bayar</label>
                        <input type="date" class="form-input" wire:model="formPaymentDate">
                        @error('formPaymentDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Penandatangan</label>
                        <select data-tom-select data-placeholder="-- Pilih Penandatangan --" class="form-input" wire:model="formSignerId">
                            <option value="">Belum ditentukan</option>
                            @foreach ($this->signerOptions as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Nama yang muncul di bagian "Dibuat oleh" slip gaji - terpisah dari siapa yang menekan Generate.</p>
                        @error('formSignerId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeFormModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveForm" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showSignerModal)
        <div class="modal-overlay" wire:click="closeSignerModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Penandatangan & Tanggal Bayar</h3>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">Dua field ini murni informasional, boleh diubah kapan saja - termasuk setelah payroll digenerate.</p>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Tanggal Bayar</label>
                        <input type="date" class="form-input" wire:model="signerFormPaymentDate">
                        @error('signerFormPaymentDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Penandatangan</label>
                        <select data-tom-select data-placeholder="-- Pilih Penandatangan --" class="form-input" wire:model="signerFormSignerId">
                            <option value="">Belum ditentukan</option>
                            @foreach ($this->signerOptions as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Nama yang muncul di bagian "Dibuat oleh" slip gaji - terpisah dari siapa yang menekan Generate.</p>
                        @error('signerFormSignerId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeSignerModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveSignerModal" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
