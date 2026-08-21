@php
    $rows = $this->componentRows;
    $earningTotal = collect($rows['earnings'])->sum('amount');
    $deductionTotal = collect($rows['deductions'])->sum('amount');
    // Diakses di sini (bukan cuma pas dirender di bawah) supaya $this->decryptFailed sudah
    // ke-set SEBELUM banner peringatan dicek, kalau salah satu dari total payroll ini gagal
    // didekripsi (beda dari kegagalan di payroll_items yang sudah ke-set lewat componentRows di atas).
    $safeGross = $this->safeGrossSalary;
    $safeDeductions = $this->safeTotalDeductions;
    $safeNet = $this->safeNetSalary;
@endphp

<div class="p-4 md:p-6">
    <style>
        .payroll-slip-columns { display:grid; grid-template-columns:1fr; gap:20px; }
        @media (min-width: 640px) {
            .payroll-slip-columns { grid-template-columns:1fr 1fr; gap:0; }
            .payroll-slip-col-left { padding-right:16px; border-right:1px dashed var(--border); }
            .payroll-slip-col-right { padding-left:16px; }
        }
    </style>

    <div style="display:flex;flex-direction:column;gap:16px;">
        <a href="{{ route('keuangan.payroll') }}" wire:navigate style="font-size:12.5px;color:var(--text-muted);text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Kembali ke daftar Payroll</a>

        @if ($this->decryptFailed)
            <div style="background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.3);border-radius:10px;padding:12px 14px;font-size:12.5px;color:#991b1b;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Sebagian nominal payroll ini gagal dibaca (data lama belum di-reencrypt, atau key enkripsi berubah) — edit/regenerate ditutup dulu sampai datanya diperbaiki. Hubungi admin sistem.
            </div>
        @endif

        @if ($this->izinSeharianGajiPokokWarning)
            <div style="background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.3);border-radius:10px;padding:12px 14px;font-size:12.5px;color:#991b1b;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                {{ $this->izinSeharianGajiPokokWarning }}
            </div>
        @endif

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;max-width:760px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;">
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Karyawan</p>
                    <p style="font-size:13.5px;font-weight:700;margin:0;">{{ $payroll->user?->name ?? '-' }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">NIP {{ $payroll->user?->nip ?? '-' }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Perusahaan</p>
                    <p style="font-size:13.5px;font-weight:700;margin:0;">{{ $payroll->payrollPeriod->company?->name ?? '-' }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Periode</p>
                    <p style="font-size:13.5px;font-weight:700;margin:0;">{{ $payroll->payrollPeriod->start_date->format('d M Y') }} - {{ $payroll->payrollPeriod->end_date->format('d M Y') }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Status</p>
                    <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $payroll->is_paid ? 'rgba(4,124,84,0.1)' : 'rgba(217,119,6,0.1)' }};color:{{ $payroll->is_paid ? '#047C54' : '#d97706' }};">
                        {{ $payroll->is_paid ? 'Sudah Dibayar' : 'Belum Dibayar' }}
                    </span>
                    @if ($payroll->slip_send_failed_at)
                        <p style="font-size:10.5px;color:#dc2626;margin:4px 0 0;" title="{{ $payroll->slip_send_error }}">
                            <i class="fa-solid fa-triangle-exclamation"></i> Gagal kirim slip {{ $payroll->slip_send_failed_at->translatedFormat('d M Y H:i') }} — {{ $payroll->slip_send_error }}
                        </p>
                    @elseif ($payroll->slip_sent_at)
                        <p style="font-size:10.5px;color:var(--text-muted);margin:4px 0 0;">
                            <i class="fa-solid fa-envelope-circle-check"></i> Slip terkirim {{ $payroll->slip_sent_at->translatedFormat('d M Y H:i') }}
                        </p>
                    @endif
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Terakhir Digenerate</p>
                    <p style="font-size:12.5px;font-weight:700;margin:0;">{{ $payroll->generatedBy?->name ?? '-' }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $payroll->generated_at?->translatedFormat('d M Y H:i') ?? '-' }}</p>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:18px;padding-top:18px;border-top:1px dashed var(--border);">
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Gaji Kotor</p>
                    <p style="font-size:15px;font-weight:700;margin:0;">{{ $safeGross !== null ? 'Rp' . number_format($safeGross, 0, ',', '.') : 'Gagal dibaca' }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Total Potongan</p>
                    <p style="font-size:15px;font-weight:700;margin:0;color:#dc2626;">{{ $safeDeductions !== null ? 'Rp' . number_format($safeDeductions, 0, ',', '.') : 'Gagal dibaca' }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Gaji Bersih</p>
                    <p style="font-size:16px;font-weight:800;margin:0;color:#047C54;">{{ $safeNet !== null ? 'Rp' . number_format($safeNet, 0, ',', '.') : 'Gagal dibaca' }}</p>
                </div>
            </div>

            <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                @if (!$payroll->is_paid && $this->canEditPayroll)
                    <button type="button" wire:click="markAsPaid" wire:confirm="Tandai payroll ini sudah dibayar?" class="btn-primary" style="padding:6px 12px;font-size:11px;">
                        <i class="fa-solid fa-check"></i> Tandai Sudah Dibayar
                    </button>
                    @if (! $this->periodHasEnded)
                        <button type="button" wire:click="regeneratePayroll" wire:confirm="Hapus dan hitung ulang payroll karyawan ini saja? Payroll karyawan lain di periode ini tidak terpengaruh." class="btn-ghost" style="padding:6px 12px;font-size:11px;color:#d97706;">
                            <i class="fa-solid fa-arrow-rotate-right"></i> Regenerate Payroll Ini
                        </button>
                    @else
                        <a href="{{ route('filament.admin.resources.payroll.payrolls.view', $payroll) }}" target="_blank" class="btn-ghost" style="padding:6px 12px;font-size:11px;color:#d97706;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Edit Manual di Admin Panel
                        </a>
                    @endif
                @endif
                <a href="{{ route('keuangan.payroll.preview', $payroll->id) }}" target="_blank" class="btn-ghost" style="padding:6px 12px;font-size:11px;">
                    <i class="fa-solid fa-eye"></i> Preview Slip Gaji
                </a>
                <button type="button" wire:click="downloadSlip" class="btn-ghost" style="padding:6px 12px;font-size:11px;">
                    <i class="fa-solid fa-download"></i> Download Slip Gaji
                </button>
                @if ($this->canEditPayroll)
                    <button type="button" wire:click="openPaymentHistoryModal" class="btn-ghost" style="padding:6px 12px;font-size:11px;">
                        <i class="fa-solid fa-clock-rotate-left"></i> Riwayat Pembayaran
                    </button>
                @endif
                @if ($payroll->is_paid && $this->canEditPayroll)
                    {{-- Email tujuan sama persis prioritasnya dengan SendPayrollSlipJob - email
                    pribadi diutamakan, fallback ke email akun kalau kosong. --}}
                    <button type="button" wire:click="sendSlipEmail" wire:confirm="Kirim slip gaji ke email {{ $payroll->user?->profile?->email_pribadi ?: $payroll->user?->email }}?" class="btn-ghost" style="padding:6px 12px;font-size:11px;color:#2563eb;">
                        <i class="fa-solid fa-envelope"></i> {{ $payroll->slip_sent_at ? 'Kirim Ulang' : 'Kirim' }} Slip ke Email
                    </button>
                    <button type="button" wire:click="openCancelPaidModal" class="btn-ghost" style="padding:6px 12px;font-size:11px;color:#dc2626;">
                        <i class="fa-solid fa-rotate-left"></i> Batalkan Tanda Sudah Dibayar
                    </button>
                @endif
            </div>

            @if (!$payroll->is_paid && $this->periodHasEnded && $this->canEditPayroll)
                <p style="font-size:10.5px;color:var(--text-faint);margin-top:10px;">
                    <i class="fa-solid fa-circle-info"></i> Periode ini sudah bukan periode terbaru — Regenerate & penyesuaian Bon dari sini ditutup. Koreksi nominal lewat edit manual per baris di admin panel (Daftar Payroll &rarr; buka payroll ini &rarr; tab Payroll Items).
                </p>
            @endif
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <div class="payroll-slip-columns">
                <div class="payroll-slip-col-left">
                    <p style="display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#047C54;margin:0 0 10px;">
                        <i class="fa-solid fa-arrow-trend-up"></i> Pendapatan
                    </p>
                    <div style="display:flex;flex-direction:column;gap:7px;">
                        @forelse ($rows['earnings'] as $row)
                            <div>
                                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;">
                                    <span style="color:{{ $row['hasValue'] ? 'var(--text-body)' : 'var(--text-faint)' }};display:flex;align-items:center;gap:5px;">
                                        {{ $row['label'] }}
                                        @if ($row['component'] && ! $payroll->is_paid && $this->canEditAdjustment)
                                            <button type="button" wire:click="openComponentEditModal('{{ $row['component'] }}')" title="Ubah {{ $row['label'] }}" style="border:none;background:none;color:var(--text-faint);cursor:pointer;padding:0;font-size:10px;"><i class="fa-solid fa-pen"></i></button>
                                        @endif
                                    </span>
                                    <span style="white-space:nowrap;text-align:right;">
                                        <span style="font-weight:{{ $row['hasValue'] ? '700' : '500' }};color:{{ $row['hasValue'] ? '#047C54' : 'var(--text-faint)' }};">Rp{{ number_format($row['amount'], 0, ',', '.') }}</span>
                                        @if ($row['note'])
                                            <span style="display:block;font-size:10px;font-weight:400;color:var(--text-faint);">({{ $row['note'] }})</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Tidak ada komponen pendapatan.</p>
                        @endforelse
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;font-weight:700;margin-top:10px;padding-top:8px;border-top:1px solid var(--border-soft);">
                        <span>Subtotal</span>
                        <span style="color:#047C54;">Rp{{ number_format($earningTotal, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="payroll-slip-col-right">
                    <p style="display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#dc2626;margin:0 0 10px;">
                        <i class="fa-solid fa-arrow-trend-down"></i> Potongan
                    </p>
                    <div style="display:flex;flex-direction:column;gap:7px;">
                        @forelse ($rows['deductions'] as $row)
                            <div>
                                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;">
                                    <span style="color:{{ $row['hasValue'] ? 'var(--text-body)' : 'var(--text-faint)' }};display:flex;align-items:center;gap:5px;">
                                        {{ $row['label'] }}
                                        @if ($row['component'] && ! $payroll->is_paid && $this->canEditAdjustment)
                                            <button type="button" wire:click="openComponentEditModal('{{ $row['component'] }}')" title="Ubah {{ $row['label'] }}" style="border:none;background:none;color:var(--text-faint);cursor:pointer;padding:0;font-size:10px;"><i class="fa-solid fa-pen"></i></button>
                                        @endif
                                    </span>
                                    <span style="white-space:nowrap;text-align:right;">
                                        <span style="font-weight:{{ $row['hasValue'] ? '700' : '500' }};color:{{ $row['hasValue'] ? '#dc2626' : 'var(--text-faint)' }};">Rp{{ number_format($row['amount'], 0, ',', '.') }}</span>
                                        @if ($row['note'])
                                            <span style="display:block;font-size:10px;font-weight:400;color:var(--text-faint);">({{ $row['note'] }})</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Tidak ada komponen potongan.</p>
                        @endforelse

                        {{-- Editor Bon (+Tambah / Ubah / Hapus) — DIPINDAH KELUAR dari loop $rows['deductions']
                        di atas (2026-08-05): tiap baris Bon (mode Detail) sekarang bisa punya NAMA sendiri
                        (bukan selalu "Bon"), jadi kalau blok ini masih di dalam loop & dicocokkan per-$row,
                        dia bakal render ULANG utuh untuk SETIAP baris Bon yang ada — render SEKALI saja
                        di sini, tidak lagi bergantung ke $row tertentu.

                        Mode "Simple" (2026-08-18): editor ini DISEMBUNYIKAN total - komponen Bon yang aktif
                        SELALU punya baris di $rows['deductions'] (fallback Rp0, lihat PayrollService::runComponent()),
                        jadi tombol pensil "Ubah" generik di baris itu sendiri sudah cukup buat isi/ubah nominal
                        langsung ke payroll_items (lihat openComponentEditModal()) - tidak perlu lagi UI
                        payroll_manual_lines terpisah untuk kasus "1 nominal per periode" ini. Mode "Detail" TETAP
                        pakai editor ini - itu satu-satunya cara punya BANYAK baris rincian bernama, tombol edit
                        generik cuma bisa 1 nilai per komponen. --}}
                        @if ($this->bonAktif && $this->bonMetode === 'detail')
                            <div style="margin-top:2px;">
                                @if (! $payroll->is_paid && ! $this->periodHasEnded && $this->canEditAdjustment)
                                    <button type="button" wire:click="openManualLineModal('bon')" title="Tambah Bon untuk payroll ini" style="border:none;background:#d97706;color:#fff;padding:2px 7px;border-radius:5px;font-size:10px;font-weight:600;cursor:pointer;">+ Tambah Bon</button>
                                @endif

                                @if ($this->bonManualLines->isNotEmpty())
                                    <div style="display:flex;flex-direction:column;gap:3px;margin-top:4px;">
                                        @foreach ($this->bonManualLines as $line)
                                            <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;font-size:11px;color:var(--text-muted);">
                                                <span>{{ $line->label ? "{$line->label}: " : '' }}Rp{{ number_format($line->amount, 0, ',', '.') }}{{ $line->note ? " ({$line->note})" : '' }}</span>
                                                @if (!$payroll->is_paid && !$this->periodHasEnded && $this->canEditAdjustment)
                                                    <span style="display:flex;gap:4px;flex-shrink:0;">
                                                        <button type="button" wire:click="openManualLineModal('bon', '{{ $line->id }}')" title="Ubah" style="border:1px solid var(--border);background:none;color:#d97706;cursor:pointer;padding:2px 7px;border-radius:5px;font-size:10px;"><i class="fa-solid fa-pen"></i> Ubah</button>
                                                        <button type="button" wire:click="deleteManualLine('{{ $line->id }}')" wire:confirm="Hapus baris Bon ini?" title="Hapus" style="border:1px solid var(--border);background:none;color:#dc2626;cursor:pointer;padding:2px 7px;border-radius:5px;font-size:10px;"><i class="fa-solid fa-trash"></i> Hapus</button>
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;font-weight:700;margin-top:10px;padding-top:8px;border-top:1px solid var(--border-soft);">
                        <span>Subtotal</span>
                        <span style="color:#dc2626;">Rp{{ number_format($deductionTotal, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php $manualLineLabel = 'Bon'; @endphp
    @if ($showManualLineModal)
        <div class="modal-overlay" wire:click="closeManualLineModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">{{ $editingManualLineId ? "Ubah {$manualLineLabel}" : "Tambah {$manualLineLabel}" }}</h3>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">Untuk {{ $payroll->user?->name }} — periode {{ $payroll->payrollPeriod->start_date->format('d M Y') }} - {{ $payroll->payrollPeriod->end_date->format('d M Y') }}.</p>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    @if ($manualLineComponent === \App\Enum\Payroll\PayrollComponentEnum::BON->value && $this->bonMetode === 'detail')
                        <div>
                            <label class="form-label">Nama Potongan (tampil di slip, gantikan "Bon")</label>
                            <input type="text" class="form-input" wire:model="manualLineLabel" placeholder="mis. Angsuran Motor">
                            @error('manualLineLabel') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="form-label">Nominal (Rp)</label>
                        <input type="number" step="0.01" class="form-input" wire:model="manualLineAmount" placeholder="0">
                        @error('manualLineAmount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Catatan (wajib diisi — alasan perubahan)</label>
                        <textarea class="form-input" rows="2" wire:model="manualLineNote"></textarea>
                        @error('manualLineNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <p style="font-size:10.5px;color:var(--text-faint);margin-top:10px;">Baru masuk ke payroll ini setelah "Regenerate Payroll Ini" dijalankan.</p>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeManualLineModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveManualLine" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showComponentEditModal)
        <div class="modal-overlay" wire:click="closeComponentEditModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Ubah {{ $editingComponentLabel }}</h3>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">Untuk {{ $payroll->user?->name }} — periode {{ $payroll->payrollPeriod->start_date->format('d M Y') }} - {{ $payroll->payrollPeriod->end_date->format('d M Y') }}. Langsung tersimpan ke slip ini, TIDAK perlu Regenerate.</p>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Nominal Baru (Rp)</label>
                        <input type="number" step="0.01" class="form-input" wire:model="componentEditAmount" placeholder="0">
                        @error('componentEditAmount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Catatan (wajib diisi — alasan perubahan)</label>
                        <textarea class="form-input" rows="2" wire:model="componentEditNote"></textarea>
                        @error('componentEditNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <p style="font-size:10.5px;color:var(--text-faint);margin-top:10px;">Perubahan ini tercatat di audit log (module "payroll_adjustment").</p>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeComponentEditModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveComponentEdit" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showCancelPaidModal)
        <div class="modal-overlay" wire:click="closeCancelPaidModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Batalkan Tanda Sudah Dibayar</h3>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">Untuk {{ $payroll->user?->name }} — periode {{ $payroll->payrollPeriod->start_date->format('d M Y') }} - {{ $payroll->payrollPeriod->end_date->format('d M Y') }}. Notifikasi "Gaji Sudah Dibayar" yang sudah terkirim ke karyawan TIDAK ditarik balik.</p>

                <div>
                    <label class="form-label">Catatan (wajib diisi — alasan pembatalan)</label>
                    <textarea class="form-input" rows="2" wire:model="cancelPaidReason"></textarea>
                    @error('cancelPaidReason') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeCancelPaidModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="unmarkAsPaid" class="btn-primary" style="background:#dc2626;">Batalkan Pembayaran</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showPaymentHistoryModal)
        <div class="modal-overlay" wire:click="closePaymentHistoryModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Riwayat Pembayaran</h3>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">{{ $payroll->user?->name }} — periode {{ $payroll->payrollPeriod->start_date->format('d M Y') }} - {{ $payroll->payrollPeriod->end_date->format('d M Y') }}.</p>

                @forelse ($this->paymentHistory as $entry)
                    <div style="padding:8px 0;border-top:1px solid var(--border-soft);">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                            <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10.5px;font-weight:600;background:{{ $entry->new_value === 'Sudah Dibayar' ? 'rgba(4,124,84,0.1)' : 'rgba(220,38,38,0.1)' }};color:{{ $entry->new_value === 'Sudah Dibayar' ? '#047C54' : '#dc2626' }};">
                                {{ $entry->new_value }}
                            </span>
                            <span style="font-size:10.5px;color:var(--text-faint);">{{ $entry->created_at->translatedFormat('d M Y H:i') }}</span>
                        </div>
                        <p style="font-size:11.5px;color:var(--text-body);margin:6px 0 0;">Oleh {{ $entry->user?->name ?? '-' }}</p>
                        @if ($entry->reasonFromLabel())
                            <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">"{{ $entry->reasonFromLabel() }}"</p>
                        @endif
                    </div>
                @empty
                    <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Belum ada riwayat pembayaran untuk payroll ini.</p>
                @endforelse

                <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                    <button type="button" wire:click="closePaymentHistoryModal" class="btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
