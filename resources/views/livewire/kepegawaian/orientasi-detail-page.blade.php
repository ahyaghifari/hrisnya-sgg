<div>
    <x-page-header title="Detail Pengajuan Orientasi" />

    @php
        $r = $this->record;
        $decisionLabels = [
            'lanjut_kontrak' => ['Lanjut Kontrak', '#047C54'],
            'perpanjang' => ['Perpanjang Orientasi', '#b45309'],
        ];
        $statusLabels = [
            'draft' => ['Draf', 'var(--text-muted)'],
            'diajukan' => ['Diajukan', '#b45309'],
            'dinilai' => ['Dinilai (nunggu HR)', '#1d4ed8'],
            'selesai' => ['Selesai', '#047C54'],
            'dibatalkan' => ['Dibatalkan', '#dc2626'],
        ];
        [$sLabel, $sColor] = $statusLabels[$r->status] ?? [$r->status, 'var(--text-muted)'];
    @endphp

    <div style="display:flex;flex-direction:column;gap:16px;">

        {{-- Info karyawan --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                <div>
                    <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 2px;">{{ $r->user?->name }}</p>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">NIP {{ $r->user?->nip ?? '-' }} &middot; {{ $r->user?->profile?->jobPosition?->name ?? '-' }}</p>
                </div>
                <span style="font-size:11px;font-weight:700;color:{{ $sColor }};white-space:nowrap;">{{ $sLabel }}</span>
            </div>
            @if ($r->userOrientation)
                <p style="font-size:12px;color:var(--text-body);margin:0 0 4px;">
                    <span style="color:var(--text-faint);">Periode:</span>
                    {{ $r->userOrientation->start_date?->format('d M Y') }} &ndash; {{ $r->userOrientation->end_date?->format('d M Y') }}
                    ({{ $r->userOrientation->duration_months }} bln)
                </p>
            @endif
            @if ($r->note)
                <p style="font-size:12.5px;color:var(--text-body);margin:0;"><span style="color:var(--text-faint);">Catatan pengaju:</span> {{ $r->note }}</p>
            @endif
            @if ($r->submitted_at)
                <p style="font-size:11px;color:var(--text-faint);margin:8px 0 0;">Diajukan {{ $r->submitted_at->translatedFormat('d F Y H:i') }}</p>
            @endif
        </div>

        {{-- Bukti kegiatan --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Bukti Kegiatan Orientasi</p>
            @forelse ($r->attachments ?? [] as $att)
                <div style="display:flex;align-items:center;gap:8px;border:1px solid var(--border-soft);border-radius:8px;padding:7px 10px;margin-bottom:6px;">
                    <i class="fa-solid fa-paperclip" style="color:var(--text-faint);font-size:12px;"></i>
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($att['path']) }}" target="_blank" rel="noopener" style="flex:1;min-width:0;font-size:12px;">{{ $att['name'] ?? 'Lampiran' }}</a>
                </div>
            @empty
                <p style="font-size:12px;color:var(--text-faint);margin:0;">Tidak ada lampiran.</p>
            @endforelse
        </div>

        {{-- Keputusan (kalau sudah) --}}
        @if (in_array($r->status, ['dinilai', 'selesai']) && $r->decision)
            @php [$dLabel, $dColor] = $decisionLabels[$r->decision] ?? [$r->decision, 'var(--text-muted)']; @endphp
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 3px;">Keputusan Penilai — {{ $r->decidedBy?->name }}</p>
                <p style="font-size:15px;font-weight:700;color:{{ $dColor }};margin:0 0 4px;">{{ $dLabel }}</p>
                @if ($r->decision_note)<p style="font-size:12.5px;color:var(--text-body);margin:0;">{{ $r->decision_note }}</p>@endif
            </div>
        @endif

        {{-- Hasil skor (read-only, kalau sudah dinilai & bukan penilai aktif) --}}
        @if ($r->assessments->isNotEmpty() && ! $this->canReview)
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;overflow-x:auto;">
                <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Hasil Penilaian per Indikator × Bulan</p>
                <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:480px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:6px 8px;">Indikator</th>
                            @foreach ($this->monthLabels as $ml)<th style="text-align:center;padding:6px 8px;white-space:nowrap;">{{ $ml }}</th>@endforeach
                            <th style="text-align:center;padding:6px 8px;">Rata2</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($r->assessments as $a)
                            @php
                                $byMonth = $a->scores->keyBy(fn ($s) => \Carbon\Carbon::parse($s->period_month)->toDateString());
                                $vals = collect($this->monthDates)->map(fn ($d) => optional($byMonth->get($d))->score)->filter(fn ($v) => $v !== null);
                            @endphp
                            <tr style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:6px 8px;">{{ $a->label }}@if ($a->comment)<span style="display:block;font-size:10.5px;color:var(--text-muted);">{{ $a->comment }}</span>@endif</td>
                                @foreach ($this->monthDates as $d)
                                    <td style="padding:6px 8px;text-align:center;">{{ optional($byMonth->get($d))->score ?? '–' }}</td>
                                @endforeach
                                <td style="padding:6px 8px;text-align:center;font-weight:700;">{{ $vals->count() ? number_format($vals->avg(), 2) : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- FORM PENILAI --}}
        @if ($this->canReview)
            <div style="background:var(--surface);border:2px solid #1d4ed8;border-radius:12px;box-shadow:var(--shadow-card);padding:18px;overflow-x:auto;">
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Form Penilaian Orientasi</p>

                @if (empty($rows))
                    <p style="font-size:12px;color:#b45309;margin:0 0 8px;">Indikator penilaian belum diatur untuk perusahaan ini. Atur dulu di <strong>Kepegawaian → Orientasi → tab Indikator Penilaian</strong>.</p>
                @else
                    <p style="font-size:11px;color:var(--text-faint);margin:0 0 8px;">Daftar indikator diatur di tab Indikator halaman Orientasi. Penilai hanya mengisi skor & catatan.</p>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:480px;">
                        <thead>
                            <tr style="border-bottom:1.5px solid var(--border);">
                                <th style="text-align:left;padding:6px 8px;">Indikator</th>
                                @foreach ($this->monthLabels as $ml)<th style="text-align:center;padding:6px 8px;white-space:nowrap;">{{ $ml }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                <tr wire:key="row-{{ $i }}" style="border-bottom:1px solid var(--border-soft);">
                                    <td style="padding:10px 8px;min-width:220px;">
                                        <p style="font-size:15px;font-weight:600;color:var(--text-heading);margin:0 0 6px;line-height:1.35;">{{ $row['label'] }}</p>
                                        <input type="text" wire:model="rows.{{ $i }}.comment" class="form-input" placeholder="Catatan indikator (opsional)" style="font-size:11px;">
                                    </td>
                                    @foreach ($this->monthLabels as $k => $ml)
                                        <td style="padding:6px 8px;text-align:center;">
                                            <select wire:model="rows.{{ $i }}.monthly.{{ $k }}" class="form-input" style="width:64px;text-align:center;padding:6px;">
                                                <option value="">–</option>
                                                @for ($n = 1; $n <= 5; $n++)<option value="{{ $n }}">{{ $n }}</option>@endfor
                                            </select>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @error('rows.*.monthly.*') <p style="font-size:11px;color:#dc2626;margin-top:6px;">Skor harus 1–5.</p> @enderror
                    @error('rows') <p style="font-size:11px;color:#dc2626;margin-top:6px;">{{ $message }}</p> @enderror
                @endif

                <div style="margin-top:16px;">
                    <label class="form-label">Keputusan</label>
                    <select wire:model.live="reviewDecision" class="form-input">
                        <option value="">-- Pilih Keputusan --</option>
                        @foreach ($this->decisionOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reviewDecision') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                @if ($reviewDecision)
                    <p style="font-size:11px;color:var(--text-faint);margin-top:6px;">
                        Detail tindak lanjut ({{ $reviewDecision === 'perpanjang' ? 'lama & mulai perpanjangan orientasi' : 'kontrak' }}) diisi oleh HR setelah keputusan disimpan.
                    </p>
                @endif

                <div style="margin-top:10px;">
                    <label class="form-label">Catatan Keputusan (wajib)</label>
                    <textarea wire:model="reviewNote" class="form-input" rows="2"></textarea>
                    @error('reviewNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                    <button type="button" wire:click="submitReview" wire:confirm="Simpan keputusan & penilaian ini?" class="btn-primary">Simpan Keputusan &amp; Penilaian</button>
                </div>

                {{-- Kembalikan ke karyawan buat perbaiki bukti (bukan keputusan lulus/gagal) --}}
                <div style="border-top:1px dashed var(--border);margin-top:16px;padding-top:12px;">
                    <label class="form-label">Minta Revisi Bukti (opsional)</label>
                    <textarea wire:model="revisionNote" class="form-input" rows="2" placeholder="Jelaskan bukti apa yang perlu diperbaiki/dilengkapi karyawan"></textarea>
                    @error('revisionNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                        <button type="button" wire:click="requestRevision" wire:confirm="Kembalikan pengajuan ke karyawan untuk revisi bukti?" class="btn-ghost" style="font-size:12px;color:#b45309;">
                            <i class="fa-solid fa-rotate-left"></i> Kembalikan untuk Revisi
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- TINDAK LANJUT HR (setelah dinilai) --}}
        @if ($this->canFollowUpExtend || $this->canFollowUpContract)
            <div style="background:var(--surface);border:2px solid #b45309;border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Tindak Lanjut HR</p>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Keputusan penilai: <strong>{{ $r->decision === 'perpanjang' ? 'Perpanjang Orientasi' : 'Lanjut Kontrak' }}</strong>.</p>

                @if ($r->decision === 'perpanjang')
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;">
                        <div style="min-width:160px;">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" wire:model.live="fuStartDate" class="form-input">
                            @error('fuStartDate') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                        </div>
                        <div style="min-width:150px;">
                            <label class="form-label">Lama (bulan)</label>
                            <select wire:model.live="fuMonths" class="form-input">
                                @foreach ([1,2,3,4,6,12] as $m)<option value="{{ $m }}">{{ $m }} bulan</option>@endforeach
                            </select>
                            @error('fuMonths') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    @if ($this->extensionPreview)
                        <p style="font-size:12px;color:var(--text-body);margin-top:8px;background:var(--warn-soft-bg);border:1px solid var(--warn-soft-border);border-radius:8px;padding:8px 10px;">
                            Periode orientasi baru: <strong>{{ $this->extensionPreview[0]->format('d M Y') }}</strong> &ndash; <strong>{{ $this->extensionPreview[1]->format('d M Y') }}</strong>
                        </p>
                    @endif
                    <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                        <button type="button" wire:click="followUpExtend" wire:confirm="Buat periode orientasi baru?" class="btn-primary">Buat Periode Baru</button>
                    </div>
                @else
                    <p style="font-size:12px;color:var(--text-body);margin:0 0 12px;">Angkat karyawan jadi <strong>Kontrak</strong>: pilih tipe karyawan tujuan & periode kontrak pertama. Status karyawan langsung berubah setelah disimpan.</p>
                    @if (empty($this->kontrakEmployeeTypes))
                        <p style="font-size:11.5px;color:#b45309;margin:0;">Belum ada tipe karyawan kategori Kontrak untuk perusahaan ini. Buat dulu di master Tipe Karyawan.</p>
                    @else
                        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;">
                            <div style="min-width:200px;flex:1;">
                                <label class="form-label">Tipe Karyawan (Kontrak)</label>
                                <select wire:model="fuEmployeeTypeId" class="form-input">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->kontrakEmployeeTypes as $id => $nm)<option value="{{ $id }}">{{ $nm }}</option>@endforeach
                                </select>
                                @error('fuEmployeeTypeId') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                            </div>
                            <div style="min-width:150px;">
                                <label class="form-label">Mulai Kontrak</label>
                                <input type="date" wire:model="fuContractStart" class="form-input">
                                @error('fuContractStart') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                            </div>
                            <div style="min-width:150px;">
                                <label class="form-label">Akhir Kontrak</label>
                                <input type="date" wire:model="fuContractEnd" class="form-input">
                                @error('fuContractEnd') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                            <button type="button" wire:click="followUpContract" wire:confirm="Buat kontrak & ubah status karyawan jadi Kontrak?" class="btn-primary">Buat Kontrak</button>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </div>
</div>
