<div>
    <x-page-header title="Payroll" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{-- Cari/company/period lewat Livewire (server-side) — SEBELUMNYA cari pakai List.js di
             browser, tapi List.js mengunci baris tbody-nya sendiri sehingga tidak ikut ke-morph
             Livewire pas company/periode ganti (baris karyawan lama nyangkut terus), jadi
             dicabut. --}}
        <div>
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
                            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" style="min-width:200px;padding-left:32px;" placeholder="Nama atau NIP...">
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
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Karyawan</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Periode</th>
                            <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Gaji Kotor</th>
                            <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Potongan</th>
                            <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Gaji Bersih</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->payrolls as $payroll)
                            <tr wire:key="'payroll-'.{{ $payroll->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;">
                                    <span style="font-weight:600;">{{ $payroll->user?->name ?? '-' }}</span>
                                    <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $payroll->user?->nip ?? '-' }}</span>
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
        </div>
    </div>

    @if ($showImportModal)
        <div class="modal-overlay" wire:click="closeImportModal">
            <div class="modal-box modal-box-xl" onclick="event.stopPropagation()" style="display:flex;flex-direction:column;gap:14px;">
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
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                        <p style="font-size:11.5px;color:var(--text-muted);margin:0;">Kolom wajib: <code>nip</code>, <code>nominal</code>. Kolom <code>nama</code> opsional — cuma buat pembanding dengan nama di sistem.</p>
                        <a href="{{ route('keuangan.payroll.import-template', $importType) }}" class="btn-ghost" style="padding:6px 12px;font-size:11.5px;white-space:nowrap;">
                            <i class="fa-solid fa-file-arrow-down"></i> Download Template
                        </a>
                    </div>

                    <div>
                        <label class="form-label">Periode Payroll</label>
                        <select class="form-input" wire:model.live="importPeriodId">
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
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">File akan dipratinjau dulu di bawah — belum tersimpan sampai kamu klik "Simpan".</p>
                        @error('importFile') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="importFile,updatedImportPeriodId" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Memproses pratinjau...</div>
                    </div>

                    @if ($importPreviewRows !== [])
                        @php $previewInvalidCount = collect($importPreviewRows)->whereNotNull('error')->count(); @endphp
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <p style="font-size:12px;margin:0;">
                                <strong>{{ count($importPreviewRows) }}</strong> baris dibaca —
                                <span style="color:#047C54;">{{ count($importPreviewRows) - $previewInvalidCount }} valid</span>,
                                <span style="color:#dc2626;">{{ $previewInvalidCount }} bermasalah</span>.
                                Baris bermasalah TIDAK akan disimpan.
                            </p>
                            <div style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;">
                                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                                    <thead>
                                        <tr style="border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--surface);">
                                            <th style="text-align:left;padding:6px 10px;">Baris</th>
                                            <th style="text-align:left;padding:6px 10px;">NIP</th>
                                            <th style="text-align:left;padding:6px 10px;">Nama Import</th>
                                            <th style="text-align:left;padding:6px 10px;">Nama di Sistem</th>
                                            <th style="text-align:right;padding:6px 10px;">Nominal</th>
                                            <th style="text-align:left;padding:6px 10px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($importPreviewRows as $row)
                                            @php
                                                $namaBeda = $row['nama_import'] && $row['nama_sistem'] && strcasecmp(trim($row['nama_import']), trim($row['nama_sistem'])) !== 0;
                                            @endphp
                                            <tr style="border-bottom:1px solid var(--border-soft);{{ $row['error'] ? 'background:rgba(220,38,38,0.06);' : '' }}">
                                                <td style="padding:6px 10px;">{{ $row['row'] }}</td>
                                                <td style="padding:6px 10px;">{{ $row['nip'] }}</td>
                                                <td style="padding:6px 10px;">{{ $row['nama_import'] ?: '-' }}</td>
                                                <td style="padding:6px 10px;{{ $namaBeda ? 'color:#d97706;font-weight:600;' : '' }}">
                                                    {{ $row['nama_sistem'] ?? '-' }}
                                                    @if ($namaBeda)
                                                        <i class="fa-solid fa-triangle-exclamation" title="Nama import beda dengan nama di sistem"></i>
                                                    @endif
                                                </td>
                                                <td style="padding:6px 10px;text-align:right;">Rp{{ number_format($row['nominal'], 0, ',', '.') }}</td>
                                                <td style="padding:6px 10px;">
                                                    @if ($row['error'])
                                                        <span style="color:#dc2626;"><i class="fa-solid fa-circle-exclamation"></i> {{ $row['error'] }}</span>
                                                    @else
                                                        <span style="color:#047C54;"><i class="fa-solid fa-circle-check"></i> Siap disimpan</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport"
                                style="align-self:flex-start;padding:10px 18px;border-radius:10px;background:#042c6c;color:#fff;font-size:13px;font-weight:600;border:none;cursor:pointer;">
                                <span wire:loading.remove wire:target="confirmImport"><i class="fa-solid fa-floppy-disk"></i> Simpan {{ count($importPreviewRows) - $previewInvalidCount }} Baris Valid</span>
                                <span wire:loading wire:target="confirmImport"><i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...</span>
                            </button>
                        </div>
                    @endif

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
