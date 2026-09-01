<div>
    <x-page-header title="Import Payroll Manual"
        subtitle="Transkripsi angka gaji final dari file Excel skema RSU ke Payroll existing — bukan hasil calculator. Dokter (Mitra) di-skip. Belum ada yang tersimpan sampai klik Konfirmasi Import." />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">

        <div style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:10px;padding:14px 16px;font-size:12.5px;color:var(--text-body);max-width:1000px;">
            <i class="fa-solid fa-circle-info"></i>
            Pilih perusahaan + periode gaji tujuan, lalu upload file <strong>.xlsx</strong> (1 sheet skema RSU).
            NIP dicocokkan ke data karyawan (exact → zero-pad 11). NIP yang tak ketemu <strong>dilewati</strong> & dilaporkan di bawah.
            Import ulang: baris payroll periode itu yang <strong>belum dibayar</strong> dihapus & dibuat ulang; kalau sudah ada yang <strong>dibayar</strong>, import ditolak.
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div style="min-width:220px;">
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="companyId" class="form-input" @disabled(! auth()->user()->canFilterByCompany())>
                    @foreach ($this->companies as $c)
                        <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:260px;">
                <label class="form-label">Periode Gaji Tujuan</label>
                <select wire:model.live="periodId" class="form-input">
                    <option value="">Pilih periode...</option>
                    @foreach ($this->periods as $p)
                        <option value="{{ $p->id }}">
                            {{ \Carbon\Carbon::parse($p->start_date)->translatedFormat('d M Y') }} – {{ \Carbon\Carbon::parse($p->end_date)->translatedFormat('d M Y') }}
                            ({{ $p->payrolls_count }} payroll)
                        </option>
                    @endforeach
                </select>
                @error('periodId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                @if ($periodId === null || $this->periods->isEmpty())
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        Belum ada periode? Buat dulu di <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate style="text-decoration:underline;">Periode Gaji</a>.
                    </p>
                @endif
            </div>
            @if ($periodId)
                <div>
                    <label class="form-label">Cari Karyawan</label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px;"></i>
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-input" style="min-width:200px;padding-left:32px;" placeholder="Nama atau NIP...">
                    </div>
                </div>
                <div>
                    <label class="form-label">Status Kirim Slip</label>
                    <select class="form-input" style="min-width:170px;" wire:model.live="slipStatus">
                        <option value="">Semua</option>
                        <option value="sent">Terkirim ({{ $this->slipStats['sent'] }})</option>
                        <option value="failed">Gagal ({{ $this->slipStats['failed'] }})</option>
                        <option value="unsent">Belum Dikirim ({{ $this->slipStats['unsent'] }})</option>
                    </select>
                </div>
            @endif
        </div>

        <div>
            <label class="form-label">File Excel (.xlsx)</label>
            <input type="file" class="form-input" wire:model="file" accept=".xlsx,.xls">
            @error('file') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
            @error('companyId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
            <div wire:loading wire:target="file,companyId,periodId" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Memproses pratinjau...</div>
        </div>

        {{-- ===== HASIL IMPORT ===== --}}
        @if ($imported)
            <div style="background:rgba(4,124,84,0.08);border:1px solid rgba(4,124,84,0.3);border-radius:10px;padding:14px 16px;font-size:13px;">
                <p style="margin:0 0 4px;font-weight:700;color:var(--brand-success);"><i class="fa-solid fa-circle-check"></i> Import selesai</p>
                <p style="margin:0;color:var(--text-body);">
                    {{ $result['dibuat'] ?? 0 }} payroll dibuat (status belum dibayar), {{ $result['dilewati'] ?? 0 }} baris dilewati.
                    Cek tabel di bawah — centang baris lalu <strong>Tandai Sudah Dibayar</strong> &amp; <strong>Kirim Slip</strong>.
                </p>
                @if (! empty($result['warnings']))
                    <details style="margin-top:8px;">
                        <summary style="cursor:pointer;font-size:12px;">{{ count($result['warnings']) }} catatan</summary>
                        <ul style="margin:6px 0 0;padding-left:18px;font-size:12px;color:var(--text-muted);">
                            @foreach ($result['warnings'] as $w)
                                <li>[{{ $w['type'] }}] {{ $w['nama'] }} — {{ $w['detail'] }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        {{-- ===== PRATINJAU ===== --}}
        @if (! empty($preview))
            @php $s = $preview['summary']; @endphp

            <div style="display:flex;flex-wrap:wrap;gap:12px;">
                @foreach ([
                    ['Cocok', $s['cocok'], 'var(--brand-success)'],
                    ['Tak cocok (dilewati)', $s['tak_cocok'], '#d97706'],
                    ['Mitra dilewati', $s['mitra_dilewati'], 'var(--text-muted)'],
                    ['Total Net', 'Rp ' . number_format($s['total_net'], 0, ',', '.'), 'var(--text-heading)'],
                ] as [$lbl, $val, $col])
                    <div style="flex:1;min-width:150px;padding:12px 14px;border:1px solid var(--border-soft);border-radius:10px;">
                        <p style="margin:0;font-size:10.5px;text-transform:uppercase;letter-spacing:.03em;color:var(--text-muted);">{{ $lbl }}</p>
                        <p style="margin:4px 0 0;font-size:18px;font-weight:800;color:{{ $col }};">{{ $val }}</p>
                    </div>
                @endforeach
            </div>

            @if (! empty($preview['warnings']))
                <div style="background:var(--warn-soft-bg);border:1px solid var(--warn-soft-border);border-radius:10px;padding:12px 14px;font-size:12.5px;">
                    <p style="margin:0 0 4px;font-weight:700;color:#b45309;">{{ count($preview['warnings']) }} peringatan (tidak memblokir import)</p>
                    <ul style="margin:0;padding-left:18px;max-height:200px;overflow-y:auto;color:#92400e;">
                        @foreach ($preview['warnings'] as $w)
                            <li>[{{ $w['type'] }}] {{ $w['nama'] }} ({{ $w['nip'] }}) — {{ $w['detail'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($preview['unmatched']))
                <div>
                    <p style="font-size:13px;font-weight:700;color:#d97706;margin:0 0 8px;"><i class="fa-solid fa-circle-xmark"></i> NIP Tak Ketemu — Dilewati ({{ count($preview['unmatched']) }})</p>
                    <div style="max-height:220px;overflow-y:auto;border:1px solid var(--border-soft);border-radius:10px;">
                        <table style="width:100%;border-collapse:collapse;font-size:12px;">
                            <thead><tr style="border-bottom:1px solid var(--border-soft);position:sticky;top:0;background:var(--surface);">
                                <th style="text-align:left;padding:6px 10px;">NIP (Excel)</th><th style="text-align:left;padding:6px 10px;">Nama</th><th style="text-align:left;padding:6px 10px;">Jabatan</th>
                            </tr></thead>
                            <tbody>
                            @foreach ($preview['unmatched'] as $u)
                                <tr style="border-bottom:1px solid var(--border-soft);"><td style="padding:6px 10px;">{{ $u['nip_excel'] }}</td><td style="padding:6px 10px;">{{ $u['nama'] }}</td><td style="padding:6px 10px;">{{ $u['jabatan'] }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (! empty($preview['matched']))
                @php $cols = \App\Services\Payroll\ManualPayrollExcelImporter::previewColumns(); @endphp
                <div>
                    <p style="font-size:13px;font-weight:700;color:var(--brand-success);margin:0 0 4px;"><i class="fa-solid fa-circle-check"></i> Akan Diimport ({{ count($preview['matched']) }})</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 8px;">Kolom komponen = jumlah per <em>source_type</em> (Jasa Unit sudah dijumlah dari 3 kolom). "Net Excel" = kolom AS; merah kalau beda dari Net hitung.</p>
                    <div style="max-height:420px;overflow:auto;border:1px solid var(--border-soft);border-radius:10px;">
                        <table style="border-collapse:collapse;font-size:11.5px;white-space:nowrap;">
                            <thead>
                                <tr style="position:sticky;top:0;z-index:2;background:var(--surface);border-bottom:2px solid var(--border-soft);">
                                    <th style="text-align:left;padding:6px 10px;position:sticky;left:0;background:var(--surface);z-index:3;">NIP</th>
                                    <th style="text-align:left;padding:6px 10px;position:sticky;left:90px;background:var(--surface);z-index:3;">Nama</th>
                                    @foreach ($cols['earnings'] as $lbl)
                                        <th style="text-align:right;padding:6px 8px;color:var(--brand-success);">{{ $lbl }}</th>
                                    @endforeach
                                    <th style="text-align:right;padding:6px 8px;font-weight:800;">BRUTO</th>
                                    @foreach ($cols['deductions'] as $lbl)
                                        <th style="text-align:right;padding:6px 8px;color:#b45309;">{{ $lbl }}</th>
                                    @endforeach
                                    <th style="text-align:right;padding:6px 8px;font-weight:800;">POTONGAN</th>
                                    <th style="text-align:right;padding:6px 8px;font-weight:800;">NET</th>
                                    <th style="text-align:right;padding:6px 8px;">Net Excel</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($preview['matched'] as $m)
                                @php $fmt = fn ($v) => $v > 0 ? number_format($v, 0, ',', '.') : '·'; @endphp
                                <tr style="border-bottom:1px solid var(--border-soft);">
                                    <td style="padding:5px 10px;position:sticky;left:0;background:var(--surface);">{{ $m['nip'] }}</td>
                                    <td style="padding:5px 10px;position:sticky;left:90px;background:var(--surface);">{{ \Illuminate\Support\Str::limit($m['nama'], 24) }}</td>
                                    @foreach ($cols['earnings'] as $key => $lbl)
                                        <td style="padding:5px 8px;text-align:right;">{{ $fmt($m['amounts'][$key] ?? 0) }}</td>
                                    @endforeach
                                    <td style="padding:5px 8px;text-align:right;font-weight:700;">{{ number_format($m['gross'], 0, ',', '.') }}</td>
                                    @foreach ($cols['deductions'] as $key => $lbl)
                                        <td style="padding:5px 8px;text-align:right;">{{ $fmt($m['amounts'][$key] ?? 0) }}</td>
                                    @endforeach
                                    <td style="padding:5px 8px;text-align:right;font-weight:700;">{{ number_format($m['deduction'], 0, ',', '.') }}</td>
                                    <td style="padding:5px 8px;text-align:right;font-weight:800;">{{ number_format($m['net'], 0, ',', '.') }}</td>
                                    <td style="padding:5px 8px;text-align:right;{{ $m['net_cocok'] ? 'color:var(--text-muted);' : 'color:#dc2626;font-weight:700;' }}">
                                        {{ number_format($m['net_excel'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport" class="btn-primary">
                        <i class="fa-solid fa-file-import"></i> Konfirmasi Import ({{ count($preview['matched']) }} payroll)
                    </button>
                    <button type="button" wire:click="cancelPreview" class="btn-ghost">Batal</button>
                </div>
            @endif
        @endif

        {{-- ===== TABEL PAYROLL PERIODE TERPILIH ===== --}}
        @if ($periodId)
            <div style="border-top:1px solid var(--border-soft);padding-top:16px;margin-top:4px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">
                        Payroll Periode
                        {{ $this->selectedPeriod?->start_date?->translatedFormat('d M') }}–{{ $this->selectedPeriod?->end_date?->translatedFormat('d M Y') }}
                        ({{ $this->payrolls->count() }})
                    </p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        @if ($this->slipStats['failed'] > 0 && $this->canUpdatePayroll)
                            <button type="button" wire:click="resendFailedSlips" wire:confirm="Kirim ulang {{ $this->slipStats['failed'] }} slip gaji yang gagal terkirim?" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:10px;cursor:pointer;background:#dc2626;color:#fff;">
                                <i class="fa-solid fa-rotate-right"></i> Kirim Ulang Gagal ({{ $this->slipStats['failed'] }})
                            </button>
                        @endif
                        @if ($this->canUpdatePayroll && $this->payrolls->where('is_paid', false)->count() > 0)
                            <button type="button" wire:click="clearUnpaid" wire:confirm="Hapus SEMUA payroll periode ini yang belum dibayar?" class="btn-ghost" style="padding:6px 12px;font-size:11.5px;color:#dc2626;">
                                <i class="fa-solid fa-trash"></i> Hapus yang Belum Dibayar
                            </button>
                        @endif
                        <a href="{{ route('keuangan.payroll') }}?periodId={{ $periodId }}" wire:navigate class="btn-ghost" style="padding:6px 12px;font-size:11.5px;">
                            <i class="fa-solid fa-up-right-from-square"></i> Buka di Payroll
                        </a>
                    </div>
                </div>

                @if (count($selected) > 0 && $this->canUpdatePayroll)
                    <div style="display:flex;align-items:center;gap:10px;background:var(--surface-soft,#f8fafc);border:1px solid var(--border-soft);border-radius:10px;padding:8px 14px;margin-bottom:10px;flex-wrap:wrap;">
                        <span style="font-size:12.5px;font-weight:600;">{{ count($selected) }} dipilih</span>
                        <button type="button" wire:click="markSelectedAsPaid" wire:confirm="Tandai {{ count($selected) }} payroll terpilih sudah dibayar?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                            <i class="fa-solid fa-check"></i> Tandai Sudah Dibayar
                        </button>
                        @if ($this->selectedAllPaid)
                            <button type="button" wire:click="sendSlipsToSelected" wire:confirm="Kirim slip gaji ke email {{ count($selected) }} karyawan terpilih?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#2563eb;">
                                <i class="fa-solid fa-envelope"></i> Kirim Slip Gaji ke Email
                            </button>
                        @endif
                    </div>
                @endif

                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                        <thead>
                            <tr style="border-bottom:1.5px solid var(--border);">
                                @if ($this->canUpdatePayroll)
                                    <th style="text-align:left;padding:10px 14px;width:36px;">
                                        <span class="custom-checkbox" style="--accent:#042c6c;">
                                            <input type="checkbox" wire:click="toggleSelectAll($event.target.checked)">
                                            <span class="box"><i class="fa-solid fa-check"></i></span>
                                        </span>
                                    </th>
                                @endif
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Karyawan</th>
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Jabatan (snapshot)</th>
                                <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Gaji Kotor</th>
                                <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Potongan</th>
                                <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Gaji Bersih</th>
                                <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                                <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->payrolls as $payroll)
                                <tr wire:key="mpi-payroll-{{ $payroll->id }}" style="border-bottom:1px solid var(--border-soft);">
                                    @if ($this->canUpdatePayroll)
                                        <td style="padding:9px 14px;">
                                            <span class="custom-checkbox" style="--accent:#042c6c;">
                                                <input type="checkbox" wire:model.live="selected" value="{{ $payroll->id }}">
                                                <span class="box"><i class="fa-solid fa-check"></i></span>
                                            </span>
                                        </td>
                                    @endif
                                    <td style="padding:9px 14px;white-space:nowrap;">
                                        <span style="font-weight:600;">{{ $payroll->user?->name ?? '-' }}</span>
                                        <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $payroll->user?->nip ?? '-' }}</span>
                                    </td>
                                    <td style="padding:9px 14px;font-size:11.5px;color:var(--text-muted);">{{ $payroll->import_meta['jabatan'] ?? '—' }}</td>
                                    @if ($payroll->decryptFailed)
                                        <td colspan="3" style="padding:9px 14px;text-align:center;color:#dc2626;font-size:11.5px;"><i class="fa-solid fa-triangle-exclamation"></i> Gagal dekripsi</td>
                                    @else
                                        <td style="padding:9px 14px;text-align:right;white-space:nowrap;">Rp{{ number_format($payroll->safeGross, 0, ',', '.') }}</td>
                                        <td style="padding:9px 14px;text-align:right;white-space:nowrap;color:#dc2626;">Rp{{ number_format($payroll->safeDeductions, 0, ',', '.') }}</td>
                                        <td style="padding:9px 14px;text-align:right;white-space:nowrap;font-weight:700;">Rp{{ number_format($payroll->safeNet, 0, ',', '.') }}</td>
                                    @endif
                                    <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                        <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $payroll->is_paid ? 'rgba(4,124,84,0.1)' : 'rgba(217,119,6,0.1)' }};color:{{ $payroll->is_paid ? '#047C54' : '#d97706' }};">
                                            {{ $payroll->is_paid ? 'Sudah Dibayar' : 'Belum Dibayar' }}
                                        </span>
                                        @if ($payroll->slip_send_failed_at)
                                            <span title="{{ $payroll->slip_send_error }}" style="display:block;font-size:9.5px;color:#dc2626;margin-top:2px;cursor:help;"><i class="fa-solid fa-triangle-exclamation"></i> Gagal Kirim</span>
                                        @elseif ($payroll->slip_sent_at)
                                            <span style="display:block;font-size:9.5px;color:var(--text-faint);margin-top:2px;"><i class="fa-solid fa-envelope-circle-check"></i> Terkirim</span>
                                        @endif
                                    </td>
                                    <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                        <div style="display:inline-flex;gap:6px;align-items:center;">
                                            <a href="{{ route('keuangan.payroll.show', $payroll->id) }}" wire:navigate class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">Lihat</a>
                                            @if ($this->canUpdatePayroll)
                                                @if (! $payroll->is_paid)
                                                    <button type="button" wire:click="markAsPaid('{{ $payroll->id }}')" wire:confirm="Tandai payroll {{ $payroll->user?->name }} sudah dibayar?" class="btn-ghost" style="padding:5px 10px;font-size:11.5px;">
                                                        <i class="fa-solid fa-check"></i> Tandai Dibayar
                                                    </button>
                                                @else
                                                    <button type="button" wire:click="sendSlip('{{ $payroll->id }}')" wire:confirm="Kirim slip gaji ke email {{ $payroll->user?->name }}?" class="btn-ghost" style="padding:5px 10px;font-size:11.5px;color:#2563eb;">
                                                        <i class="fa-solid fa-envelope"></i> Kirim Email
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $this->canUpdatePayroll ? 8 : 7 }}" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada payroll di periode ini — upload file di atas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
