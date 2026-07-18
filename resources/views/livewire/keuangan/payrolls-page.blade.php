<div>
    <x-page-header title="Payroll" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{--
            List.js (headless — tidak maksa style, lihat resources/js/app.js) urus cari/sortir/
            pagination, instan di browser. Container INI (#payrolls-list) harus membungkus
            SEMUA elemen yang List.js pakai — input `.search`, header `.sort`, body `.list`,
            DAN `.pagination` — kalau salah satu ada di LUAR container ini, List.js tidak akan
            pernah nemu elemen itu (ini yang bikin cari sebelumnya tidak berfungsi).
            company_id/periodId tetap lewat Livewire karena itu ganti data dari database —
            wire:ignore.self cuma mencegah Livewire menimpa atribut container ini sendiri,
            anak-anaknya (termasuk select company/periode) tetap ke-bind Livewire normal.
        --}}
        <div id="payrolls-list" data-list-table data-list-value-names="name,nip" data-list-page-size="15" wire:ignore.self>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                    <x-company-filter :companies="$this->companies" wire:model.live="companyId" />
                    <div>
                        <label class="form-label">Periode Gaji</label>
                        <select class="form-input" style="min-width:220px;" wire:model.live="periodId">
                            <option value="">Semua periode</option>
                            @foreach ($this->periods as $period)
                                <option value="{{ $period->id }}">{{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Cari Karyawan</label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px;"></i>
                            <input type="search" class="form-input search" style="min-width:200px;padding-left:32px;" placeholder="Nama atau NIP...">
                        </div>
                    </div>
                </div>

                {{-- Koperasi sengaja DICABUT dari dropdown ini (bukan dihapus) — sudah ada
                     tabel master `user_koperasi` (halaman Koperasi) yang otomatis sync ke
                     payroll_manual_lines tiap Generate, jadi input manual per periode di sini
                     jadi redundan. Disembunyikan dulu, bukan dihapus permanen. --}}
                @if ($this->insentifMetode === 'import' || $this->bonAktif)
                    <div class="dropdown">
                        <button type="button" style="padding:9px 16px;font-size:12.5px;font-weight:600;border:none;border-radius:12px;cursor:pointer;background:#047C54;color:#fff;">
                            <i class="fa-solid fa-file-import"></i> Import <i class="fa-solid fa-chevron-down text-[10px]"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" style="display:flex;flex-direction:column;gap:6px;">
                            @if ($this->insentifMetode === 'import')
                                <a href="{{ route('keuangan.import-insentif') }}" wire:navigate class="dropdown-item" style="background:#047C54;color:#fff;"><i class="fa-solid fa-hand-holding-dollar" style="color:#fff;"></i> Insentif</a>
                            @endif
                            {{-- @if ($this->koperasiAktif)
                                <button type="button" wire:click="openManualLineModal('koperasi')" class="dropdown-item" style="background:#4f46e5;color:#fff;"><i class="fa-solid fa-people-group" style="color:#fff;"></i> Koperasi</button>
                            @endif --}}
                            @if ($this->bonAktif)
                                <button type="button" wire:click="openManualLineModal('bon')" class="dropdown-item" style="background:#d97706;color:#fff;"><i class="fa-solid fa-money-bill-transfer" style="color:#fff;"></i> Bon</button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:16px;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th class="sort" data-sort="name" style="text-align:left;padding:10px 14px;white-space:nowrap;cursor:pointer;">Nama Karyawan <i class="fa-solid fa-sort" style="font-size:9px;color:var(--text-faint);"></i></th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Periode</th>
                            <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Gaji Kotor</th>
                            <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Potongan</th>
                            <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Gaji Bersih</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="list">
                        @forelse ($this->payrolls as $payroll)
                            <tr style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;">
                                    <span class="name" style="font-weight:600;">{{ $payroll->user?->name ?? '-' }}</span>
                                    <span class="nip" style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $payroll->user?->nip ?? '-' }}</span>
                                </td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $payroll->payrollPeriod->start_date->format('d M Y') }} - {{ $payroll->payrollPeriod->end_date->format('d M Y') }}</td>
                                <td style="padding:9px 14px;text-align:right;white-space:nowrap;">Rp{{ number_format($payroll->gross_salary, 0, ',', '.') }}</td>
                                <td style="padding:9px 14px;text-align:right;white-space:nowrap;color:#dc2626;">Rp{{ number_format($payroll->total_deductions, 0, ',', '.') }}</td>
                                <td style="padding:9px 14px;text-align:right;white-space:nowrap;font-weight:700;">Rp{{ number_format($payroll->net_salary, 0, ',', '.') }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $payroll->is_paid ? 'rgba(4,124,84,0.1)' : 'rgba(217,119,6,0.1)' }};color:{{ $payroll->is_paid ? '#047C54' : '#d97706' }};">
                                        {{ $payroll->is_paid ? 'Sudah Dibayar' : 'Belum Dibayar' }}
                                    </span>
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <a href="{{ route('keuangan.payroll.show', $payroll->id) }}" wire:navigate class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">Lihat</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada payroll untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-top:14px;">
                <button type="button" class="list-prev btn-ghost" style="padding:6px 14px;font-size:12px;">
                    <i class="fa-solid fa-chevron-left" style="font-size:10px;"></i> Sebelumnya
                </button>
                <ul class="pagination" style="display:flex;flex-wrap:wrap;gap:4px;list-style:none;padding:0;margin:0;"></ul>
                <button type="button" class="list-next btn-ghost" style="padding:6px 14px;font-size:12px;">
                    Selanjutnya <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                </button>
            </div>
        </div>
    </div>

    @if ($showManualLineModal)
        <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeManualLineModal">
            <div style="background:var(--surface);border-radius:14px;padding:20px;max-width:420px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    Tambah {{ $manualLineComponent === 'koperasi' ? 'Koperasi' : 'Bon' }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Periode Gaji</label>
                        <select class="form-input" wire:model="periodId">
                            <option value="">Pilih periode</option>
                            @foreach ($this->periods as $period)
                                <option value="{{ $period->id }}">{{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}</option>
                            @endforeach
                        </select>
                        @error('periodId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Karyawan</label>
                        <select class="form-input" wire:model="manualLineUserId">
                            <option value="">Pilih karyawan</option>
                            @foreach ($this->employeesForManualLine as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        @error('manualLineUserId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Nominal (Rp)</label>
                        <input type="number" step="0.01" class="form-input" wire:model="manualLineAmount" placeholder="0">
                        @error('manualLineAmount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea class="form-input" rows="2" wire:model="manualLineNote"></textarea>
                    </div>
                </div>

                <p style="font-size:10.5px;color:var(--text-faint);margin-top:10px;">Baru masuk ke payroll setelah Generate/Regenerate dijalankan untuk periode yang dipilih.</p>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeManualLineModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveManualLine" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
