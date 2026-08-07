<div>
    <x-page-header title="Omset" />

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
                    Belum ada perusahaan dengan komponen Jasa Pelayanan aktif. Aktifkan dulu di halaman <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate style="color:#042c6c;font-weight:600;">Komponen Gaji</a>.
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

                {{-- Rincian Omset --}}
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px;">
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;"><i class="fa-solid fa-hand-holding-medical"></i> Rincian Omset</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">
                        Nominal omset per kategori untuk periode ini — dijumlah jadi Total Omset dipakai perhitungan Jasa Pelayanan.
                        @can(\App\Enum\CustomPermissionEnum::LIHAT_MAPPING_KOMPONEN_GAJI->value)
                            Kelola daftar kategorinya di halaman <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate style="color:#042c6c;font-weight:600;">Mapping Komponen Gaji</a>.
                        @endcan
                    </p>

                    @if ($this->categories->isEmpty())
                        <p style="font-size:11.5px;color:var(--text-faint);">Belum ada Kategori Omset untuk perusahaan ini.</p>
                    @else
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            @foreach ($this->categories as $c)
                                <div>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <span style="flex:1;font-size:12.5px;font-weight:600;">{{ $c->name }}</span>
                                        <input type="number" step="0.01" class="form-input" style="max-width:220px;font-size:12.5px;padding:7px 10px;" wire:model="omsetValues.{{ $c->id }}" placeholder="Nominal (Rp)">
                                    </div>
                                    @error("omsetValues.{$c->id}") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;text-align:right;">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding-top:12px;border-top:1px dashed var(--border-soft);">
                            <span style="font-size:12.5px;font-weight:700;">Total Omset: Rp {{ number_format($this->totalOmset, 0, ',', '.') }}</span>
                            @if ($this->canEdit)
                                <button type="button" wire:click="saveOmset" wire:loading.attr="disabled" wire:target="saveOmset" class="btn-primary" style="padding:9px 20px;font-size:12.5px;">Simpan Omset</button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Job Evaluation per Jabatan — READ-ONLY, cuma konteks. Edit di Kepegawaian. --}}
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px;">
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;"><i class="fa-solid fa-table-list"></i> Job Evaluation per Jabatan</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Skor JE tiap jabatan aktif perusahaan ini (referensi saja) — Total JE jadi pembagi rasio Jasa Pelayanan. Untuk ubah skornya, buka halaman <a href="{{ route('kepegawaian.job-evaluation') }}" wire:navigate style="color:#042c6c;font-weight:600;">Job Evaluation</a> di menu Kepegawaian.</p>

                    @if ($this->jobPositions->isEmpty())
                        <p style="font-size:11.5px;color:var(--text-faint);">Belum ada Jabatan (status Approved) untuk perusahaan ini.</p>
                    @else
                        <div style="overflow-x:auto;">
                            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                                <thead>
                                    <tr style="border-bottom:1.5px solid var(--border);">
                                        <th style="text-align:left;padding:8px 10px;width:40px;">No</th>
                                        <th style="text-align:left;padding:8px 10px;">Nama Jabatan</th>
                                        <th style="text-align:right;padding:8px 10px;width:120px;">JE Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->jobPositions as $i => $p)
                                        <tr style="border-bottom:1px solid var(--border-soft);">
                                            <td style="padding:6px 10px;color:var(--text-faint);">{{ $i + 1 }}</td>
                                            <td style="padding:6px 10px;font-weight:600;">{{ $p->name }}</td>
                                            <td style="padding:6px 10px;text-align:right;">{{ number_format($p->job_evaluation_score ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--border-soft);">
                            <span style="font-size:12.5px;font-weight:700;">Total JE: {{ number_format($this->totalJe) }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
