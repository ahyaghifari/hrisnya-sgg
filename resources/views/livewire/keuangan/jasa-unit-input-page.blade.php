<div>
    <x-page-header title="Input Jasa Unit" />

    <div class="mt-4 doc-type-layout">
        @if (auth()->user()->canFilterByCompany())
        <div class="doc-type-nav">
            @foreach ($this->companies as $company)
                <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn {{ $companyId === $company->id ? 'is-active' : '' }}">
                    {{ $company->name }}
                </button>
            @endforeach
        </div>
        @endif

        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:16px;">
            @if ($this->companies->isEmpty())
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-muted);font-size:12.5px;">
                    <i class="fa-solid fa-circle-info"></i>
                    Belum ada perusahaan dengan komponen Jasa Unit aktif.
                    @can(\App\Enum\CustomPermissionEnum::LIHAT_KOMPONEN_GAJI->value)
                        Aktifkan dulu di halaman <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate style="color:#042c6c;font-weight:600;">Komponen Gaji</a>.
                    @endcan
                </div>
            @elseif ($this->periods->isEmpty())
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-muted);font-size:12.5px;">
                    <i class="fa-solid fa-circle-info"></i>
                    Belum ada Periode Gaji untuk perusahaan ini. Buat dulu di halaman <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate style="color:#042c6c;font-weight:600;">Periode Gaji</a>.
                </div>
            @else
                <div>
                    <label class="form-label">Periode Gaji</label>
                    <select class="form-input" style="max-width:320px;" wire:change="selectPeriod($event.target.value)">
                        @foreach ($this->periods as $period)
                            <option value="{{ $period->id }}" @selected($period->id === $periodId)>{{ $period->start_date->format('d M Y') }} - {{ $period->end_date->format('d M Y') }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px;">
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;"><i class="fa-solid fa-hospital-user"></i> Jasa Unit per Karyawan</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">
                        Pilih karyawan, jasa (otomatis difilter sesuai divisi jabatan aktif karyawan itu), lalu nominal — bisa tambah beberapa baris.
                        @can('viewAny', \App\Models\Payroll\JasaUnitType::class)
                            Kelola daftar jasa & divisinya di halaman <a href="{{ route('keuangan.jasa-unit-types') }}" wire:navigate style="color:#042c6c;font-weight:600;">Jasa Unit</a>.
                        @endcan
                    </p>

                    @if ($this->eligibleEmployees->isEmpty())
                        <p style="font-size:11.5px;color:var(--text-faint);margin:0 0 12px;">
                            <i class="fa-solid fa-circle-info"></i> Belum ada karyawan aktif yang tipe karyawannya terdaftar sebagai penerima Jasa Unit.
                            @can(\App\Enum\CustomPermissionEnum::LIHAT_PENERIMA_KOMPONEN_GAJI->value)
                                Atur dulu di halaman <a href="{{ route('keuangan.penerima-komponen-gaji') }}" wire:navigate style="color:#042c6c;font-weight:600;">Penerima Komponen Gaji</a>.
                            @endcan
                        </p>
                    @endif

                    <div style="display:flex;flex-direction:column;gap:10px;">
                        @foreach ($rows as $i => $row)
                            @php
                                $jasaOptions = $this->jasaOptionsFor($row['user_id']);
                            @endphp
                            <div wire:key="jasa-unit-row-{{ $row['id'] ?? 'new-' . $i }}" style="display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;">
                                <div style="flex:1;min-width:220px;" wire:ignore>
                                    <select class="form-input" data-tom-select data-placeholder="-- Cari Karyawan --" style="font-size:12.5px;padding:7px 10px;" wire:model.live="rows.{{ $i }}.user_id">
                                        <option value="">-- Pilih Karyawan --</option>
                                        @foreach ($this->eligibleEmployees as $user)
                                            <option value="{{ $user->id }}" @selected($row['user_id'] === $user->id)>{{ $user->name }} ({{ $user->nip }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div style="flex:1;min-width:220px;">
                                    <select class="form-input" style="font-size:12.5px;padding:7px 10px;" wire:model="rows.{{ $i }}.jasa_unit_type_id" @disabled($jasaOptions->isEmpty())>
                                        <option value="">{{ $jasaOptions->isEmpty() ? '-- Pilih karyawan dulu --' : '-- Pilih Jasa --' }}</option>
                                        @foreach ($jasaOptions as $jasa)
                                            <option value="{{ $jasa->id }}" @selected($row['jasa_unit_type_id'] === $jasa->id)>{{ $jasa->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <input type="number" step="0.01" class="form-input" style="max-width:200px;font-size:12.5px;padding:7px 10px;" wire:model="rows.{{ $i }}.amount" placeholder="Nominal (Rp)">
                                @if ($this->canEdit)
                                    <button type="button" wire:click="removeRow({{ $i }})" style="border:none;background:none;color:#dc2626;cursor:pointer;padding:7px;"><i class="fa-solid fa-trash"></i></button>
                                @endif

                                <div style="flex-basis:100%;">
                                    @error("rows.{$i}.user_id") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                    @error("rows.{$i}.jasa_unit_type_id") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                    @error("rows.{$i}.amount") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endforeach
                        @if (empty($rows))
                            <p style="font-size:11.5px;color:var(--text-faint);">Belum ada baris. Klik "Tambah Baris" di bawah.</p>
                        @endif
                    </div>

                    <div style="display:flex;gap:10px;margin-top:14px;">
                        @if ($this->canEdit)
                            <button type="button" wire:click="addRow" class="btn-ghost" style="padding:9px 16px;font-size:12.5px;"><i class="fa-solid fa-plus"></i> Tambah Baris</button>
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="padding:9px 20px;font-size:12.5px;">Simpan</button>
                            <button type="button" wire:click="openImportModal" class="btn-ghost" style="padding:9px 16px;font-size:12.5px;"><i class="fa-solid fa-file-import"></i> Import Excel</button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal import full-screen: pilih file -> pratinjau tervalidasi -> konfirmasi. Tidak
         menulis apa pun ke DB sebelum "Konfirmasi Import" diklik (lihat
         JasaUnitInputPage::updatedImportFile()/confirmImport()). --}}
    @if ($showImportModal)
        <div class="modal-overlay" wire:click="closeImportModal">
            <div class="modal-box modal-box-xl" style="width:95vw;height:92vh;max-width:1400px;display:flex;flex-direction:column;margin:16px auto;" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-shrink:0;">
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;"><i class="fa-solid fa-file-import"></i> Import Nominal Jasa Unit</h3>
                    <button type="button" wire:click="closeImportModal" style="background:none;border:none;font-size:18px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="flex-shrink:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:12px;background:var(--surface-alt);border-radius:10px;margin-bottom:14px;">
                    <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" style="font-size:12px;max-width:280px;">
                    <a href="{{ route('keuangan.jasa-unit.import-template') }}" class="btn-ghost" style="padding:7px 14px;font-size:12px;text-decoration:none;"><i class="fa-solid fa-download"></i> Unduh Template</a>
                    <span wire:loading wire:target="importFile" style="font-size:11.5px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Memproses pratinjau...</span>
                </div>
                @error('importFile') <p style="font-size:11px;color:#dc2626;margin:-8px 0 14px;">{{ $message }}</p> @enderror

                <div style="flex:1;min-height:0;overflow-y:auto;">
                    @if (! empty($importPreviewRows))
                        @php $errorCount = collect($importPreviewRows)->filter(fn ($r) => $r['error'])->count(); @endphp
                        <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">
                            {{ count($importPreviewRows) }} baris terbaca —
                            <strong style="color:#047C54;">{{ count($importPreviewRows) - $errorCount }} valid</strong>
                            @if ($errorCount > 0)
                                , <strong style="color:#dc2626;">{{ $errorCount }} bermasalah</strong> (tidak akan disimpan)
                            @endif
                        </p>
                        <table style="width:100%;border-collapse:collapse;font-size:12px;">
                            <thead>
                                <tr style="border-bottom:1.5px solid var(--border);">
                                    <th style="text-align:left;padding:6px 8px;">Baris</th>
                                    <th style="text-align:left;padding:6px 8px;">NIP</th>
                                    <th style="text-align:left;padding:6px 8px;">Nama</th>
                                    <th style="text-align:left;padding:6px 8px;">Kode</th>
                                    <th style="text-align:left;padding:6px 8px;">Jasa</th>
                                    <th style="text-align:right;padding:6px 8px;">Nominal</th>
                                    <th style="text-align:left;padding:6px 8px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importPreviewRows as $r)
                                    <tr style="border-bottom:1px solid var(--border-soft);{{ $r['error'] ? 'background:rgba(220,38,38,0.06);' : '' }}">
                                        <td style="padding:6px 8px;">{{ $r['row'] }}</td>
                                        <td style="padding:6px 8px;">{{ $r['nip'] }}</td>
                                        <td style="padding:6px 8px;">{{ $r['nama'] ?? '-' }}</td>
                                        <td style="padding:6px 8px;">{{ $r['kode'] }}</td>
                                        <td style="padding:6px 8px;">{{ $r['jasa'] ?? '-' }}</td>
                                        <td style="padding:6px 8px;text-align:right;">{{ number_format($r['nominal'], 2) }}</td>
                                        <td style="padding:6px 8px;">
                                            @if ($r['error'])
                                                <span style="color:#dc2626;">{{ $r['error'] }}</span>
                                            @else
                                                <span style="color:#047C54;"><i class="fa-solid fa-check"></i> Valid</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @elseif (! empty($importErrors))
                        <p style="font-size:12.5px;color:#dc2626;font-weight:600;margin:0 0 6px;">Baris bermasalah saat import terakhir:</p>
                        <ul style="font-size:12px;color:#dc2626;margin:0;padding-left:18px;">
                            @foreach ($importErrors as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p style="font-size:12.5px;color:var(--text-faint);text-align:center;padding:40px 0;">Pilih file Excel untuk melihat pratinjau.</p>
                    @endif
                </div>

                <div style="flex-shrink:0;display:flex;justify-content:flex-end;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft);">
                    <button type="button" wire:click="closeImportModal" class="btn-ghost" style="padding:9px 18px;font-size:12.5px;">Batal</button>
                    <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport"
                        @disabled(empty($importPreviewRows) || collect($importPreviewRows)->every(fn ($r) => $r['error']))
                        class="btn-primary" style="padding:9px 20px;font-size:12.5px;">
                        <span wire:loading.remove wire:target="confirmImport"><i class="fa-solid fa-floppy-disk"></i> Konfirmasi Import</span>
                        <span wire:loading wire:target="confirmImport"><i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
