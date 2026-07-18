<div class="p-4 md:p-6">
    <x-page-header title="Periode Gaji" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{--
            List.js (headless, lihat resources/js/app.js) urus sort+pagination di browser,
            pola sama halaman Payroll. Company tetap lewat Livewire karena itu ganti data dari
            database — wire:ignore.self cuma mencegah Livewire menimpa atribut container ini
            sendiri, anak-anaknya (termasuk select company) tetap ke-bind Livewire normal.
        --}}
        <div id="payroll-periods-list" data-list-table data-list-value-names="company" wire:ignore.self>
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
                            <th class="sort" data-sort="company" style="text-align:left;padding:10px 14px;white-space:nowrap;cursor:pointer;">Perusahaan <i class="fa-solid fa-sort" style="font-size:9px;color:var(--text-faint);"></i></th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Dari Tanggal</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Sampai Tanggal</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Status Generate</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="list">
                        @forelse ($this->periods as $period)
                            <tr style="border-bottom:1px solid var(--border-soft);">
                                <td class="company" style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $period->company?->name ?? '-' }}</td>
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
                                            <button type="button" wire:click="generatePayroll('{{ $period->id }}')" wire:confirm="Generate payroll untuk semua karyawan aktif di perusahaan ini? Aksi ini tidak bisa dibatalkan." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#047C54;">
                                                <i class="fa-solid fa-cog"></i> Generate Payroll
                                            </button>
                                        @else
                                            <button type="button" wire:click="regeneratePayroll('{{ $period->id }}')" wire:confirm="Hapus dan generate ulang seluruh payroll yang BELUM dibayar di periode ini? Payroll yang sudah dibayar tidak akan terpengaruh." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#d97706;">
                                                <i class="fa-solid fa-arrow-rotate-right"></i> Regenerate Payroll
                                            </button>
                                        @endif
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
        <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:50;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeFormModal">
            <div style="background:var(--surface);border-radius:14px;padding:20px;max-width:420px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
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
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeFormModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveForm" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
