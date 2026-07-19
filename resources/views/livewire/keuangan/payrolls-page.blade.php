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

                <div style="display:flex;gap:8px;">
                    {{-- Koperasi sengaja DICABUT dari sini (bukan dihapus) — sudah ada tabel
                         master `user_koperasi` (halaman Koperasi) yang otomatis sync ke
                         payroll_manual_lines tiap Generate, jadi input manual per periode di
                         sini jadi redundan. Disembunyikan dulu, bukan dihapus permanen. --}}
                    @if ($this->insentifMetode === 'import')
                        <button type="button" wire:click="openImportModal('insentif')" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;font-size:12.5px;font-weight:600;border:none;border-radius:12px;cursor:pointer;background:#047C54;color:#fff;">
                            <i class="fa-solid fa-hand-holding-dollar"></i> Import Insentif
                        </button>
                    @endif
                    @if ($this->bonAktif)
                        <button type="button" wire:click="openImportModal('bon')" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;font-size:12.5px;font-weight:600;border:none;border-radius:12px;cursor:pointer;background:#d97706;color:#fff;">
                            <i class="fa-solid fa-money-bill-transfer"></i> Import Bon
                        </button>
                    @endif
                </div>
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

    @if ($showImportModal)
        <div class="modal-overlay" wire:click="closeImportModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()" style="display:flex;flex-direction:column;gap:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:18px;font-weight:700;color:var(--text-heading);margin:0;">
                        Import {{ $importType === 'insentif' ? 'Insentif' : 'Bon' }}
                    </h3>
                    <button type="button" wire:click="closeImportModal" class="btn-ghost" style="padding:6px 12px;font-size:12px;">
                        <i class="fa-solid fa-xmark"></i> Tutup
                    </button>
                </div>

                <p style="font-size:12px;color:var(--text-muted);margin:0;">
                    Perusahaan: <strong>{{ $this->companies->firstWhere('id', $companyId)?->name }}</strong>
                </p>

                @if ($importType === 'insentif' && $this->insentifMetode !== 'import')
                    <div style="background:rgba(217,119,6,0.08);border:1px solid rgba(217,119,6,0.3);border-radius:10px;padding:12px 14px;font-size:12.5px;color:#92400e;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Metode Insentif perusahaan ini masih <strong>"Master"</strong> (input manual lewat halaman Insentif), bukan "Import".
                        Ubah dulu di halaman Komponen Gaji kalau memang mau pakai import Excel.
                    </div>
                @elseif ($importType === 'bon' && ! $this->bonAktif)
                    <div style="background:rgba(217,119,6,0.08);border:1px solid rgba(217,119,6,0.3);border-radius:10px;padding:12px 14px;font-size:12.5px;color:#92400e;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Komponen <strong>Bon</strong> belum aktif untuk perusahaan ini.
                        Aktifkan dulu di halaman Komponen Gaji kalau memang mau pakai Bon.
                    </div>
                @else
                    <div>
                        <label class="form-label">Periode Payroll</label>
                        <select class="form-input" wire:model="importPeriodId">
                            <option value="">Pilih periode yang sudah digenerate</option>
                            @foreach ($this->importPeriods as $period)
                                <option value="{{ $period->id }}">{{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}</option>
                            @endforeach
                        </select>
                        @if ($this->importPeriods->isEmpty())
                            <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Belum ada periode yang digenerate untuk perusahaan ini.</p>
                        @endif
                        @error('importPeriodId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">File Excel (.xlsx)</label>
                        <input type="file" class="form-input" wire:model="importFile" accept=".xlsx,.xls,.csv">
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kolom wajib: <code>nip</code>, <code>nominal</code>. Upload ulang untuk NIP yang sama akan MENGGANTI nominal sebelumnya, bukan menambah.</p>
                        @error('importFile') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="importFile" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengunggah...</div>
                    </div>

                    <button type="button" wire:click="runImport" wire:loading.attr="disabled" wire:target="runImport"
                        style="align-self:flex-start;padding:10px 18px;border-radius:10px;background:#042c6c;color:#fff;font-size:13px;font-weight:600;border:none;cursor:pointer;">
                        <span wire:loading.remove wire:target="runImport"><i class="fa-solid fa-upload"></i> Import</span>
                        <span wire:loading wire:target="runImport"><i class="fa-solid fa-spinner fa-spin"></i> Memproses...</span>
                    </button>

                    @if ($importSuccessCount !== null)
                        <div style="background:{{ $importErrors === [] ? 'rgba(4,124,84,0.08)' : 'rgba(217,119,6,0.08)' }};border-radius:10px;padding:12px 14px;font-size:12.5px;">
                            <p style="margin:0 0 4px;font-weight:600;">{{ $importSuccessCount }} baris berhasil diimport.</p>
                            @if ($importErrors !== [])
                                <p style="margin:6px 0 4px;font-weight:600;color:#92400e;">{{ count($importErrors) }} baris gagal:</p>
                                <ul style="margin:0;padding-left:18px;color:#92400e;">
                                    @foreach ($importErrors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
