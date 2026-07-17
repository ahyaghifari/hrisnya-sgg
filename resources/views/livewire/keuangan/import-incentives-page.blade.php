<div class="p-4 md:p-6">
    <x-page-header title="Import Insentif" />

    <div class="mt-4" style="max-width:560px;display:flex;flex-direction:column;gap:14px;">
        <div>
            <label class="form-label">Perusahaan</label>
            <select class="form-input" wire:model.live="companyId">
                @foreach ($this->companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
        </div>

        @if ($this->metode !== 'import')
            <div style="background:rgba(217,119,6,0.08);border:1px solid rgba(217,119,6,0.3);border-radius:10px;padding:12px 14px;font-size:12.5px;color:#92400e;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Metode Insentif perusahaan ini masih <strong>"Master"</strong> (input manual lewat halaman Insentif), bukan "Import".
                Ubah dulu di halaman Komponen Gaji kalau memang mau pakai import Excel.
            </div>
        @else
            <div>
                <label class="form-label">Periode Payroll</label>
                <select class="form-input" wire:model="payrollPeriodId">
                    <option value="">Pilih periode yang sudah digenerate</option>
                    @foreach ($this->periods as $period)
                        <option value="{{ $period->id }}">{{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}</option>
                    @endforeach
                </select>
                @if ($this->periods->isEmpty())
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Belum ada periode yang digenerate untuk perusahaan ini.</p>
                @endif
                @error('payrollPeriodId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">File Excel (.xlsx)</label>
                <input type="file" class="form-input" wire:model="file" accept=".xlsx,.xls,.csv">
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kolom wajib: <code>nip</code>, <code>nominal</code>. Upload ulang untuk NIP yang sama akan MENGGANTI nominal sebelumnya, bukan menambah.</p>
                @error('file') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                <div wire:loading wire:target="file" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengunggah...</div>
            </div>

            <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import"
                style="align-self:flex-start;padding:10px 18px;border-radius:10px;background:#042c6c;color:#fff;font-size:13px;font-weight:600;border:none;cursor:pointer;">
                <span wire:loading.remove wire:target="import"><i class="fa-solid fa-upload"></i> Import</span>
                <span wire:loading wire:target="import"><i class="fa-solid fa-spinner fa-spin"></i> Memproses...</span>
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
