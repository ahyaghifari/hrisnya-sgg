<div>
    <x-page-header title="Detail Pengajuan Orientasi" />

    @php
        $r = $this->record;
        $decisionLabels = [
            'lulus' => ['Lulus', '#047C54'],
            'perpanjang' => ['Perpanjang Orientasi', '#b45309'],
            'tidak_lulus' => ['Tidak Lulus', '#dc2626'],
        ];
    @endphp

    <div style="display:flex;flex-direction:column;gap:16px;">

        {{-- Info karyawan --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 2px;">{{ $r->user?->name }}</p>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">NIP {{ $r->user?->nip ?? '-' }} &middot; {{ $r->user?->profile?->jobPosition?->name ?? '-' }}</p>
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
        @if ($r->status === 'selesai' && $r->decision)
            @php [$dLabel, $dColor] = $decisionLabels[$r->decision] ?? [$r->decision, 'var(--text-muted)']; @endphp
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 3px;">Keputusan Penilai — {{ $r->decidedBy?->name }}</p>
                <p style="font-size:15px;font-weight:700;color:{{ $dColor }};margin:0 0 4px;">{{ $dLabel }}</p>
                @if ($r->decision_note)<p style="font-size:12.5px;color:var(--text-body);margin:0;">{{ $r->decision_note }}</p>@endif
            </div>
        @endif

        {{-- Skor (kalau sudah dinilai) --}}
        @if (! empty($r->scores))
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Hasil Penilaian per Indikator</p>
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    @foreach ($r->scores as $sc)
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:6px 4px;">{{ $sc['criterion'] }}</td>
                            <td style="padding:6px 4px;text-align:right;font-weight:600;white-space:nowrap;">{{ $sc['score'] }}</td>
                            <td style="padding:6px 4px;color:var(--text-muted);">{{ $sc['comment'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        {{-- FORM PENILAI --}}
        @if ($this->canApprove)
            <div style="background:var(--surface);border:2px solid var(--brand-primary,#1d4ed8);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                @if ($this->isFinalStep)
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Form Penilaian Orientasi</p>

                    @if (empty($this->criteria))
                        <p style="font-size:12px;color:#b45309;margin:0 0 12px;">Indikator penilaian orientasi belum diatur untuk perusahaan ini. Hubungi HRD (menu Kepegawaian → Indikator Orientasi).</p>
                    @endif

                    @foreach ($this->criteria as $i => $c)
                        <div style="border-bottom:1px solid var(--border-soft);padding:8px 0;">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
                                <div style="flex:1;min-width:0;">
                                    @if ($c['group_label'])<p style="font-size:10px;color:var(--text-faint);text-transform:uppercase;margin:0;">{{ $c['group_label'] }}</p>@endif
                                    <p style="font-size:12.5px;color:var(--text-heading);margin:0;">{{ $c['name'] }}</p>
                                </div>
                                <input type="number" step="0.5" min="0" max="{{ $c['max_score'] }}" wire:model="reviewScores.{{ $i }}.score" class="form-input" style="width:80px;" placeholder="/ {{ $c['max_score'] }}">
                            </div>
                            <input type="text" wire:model="reviewScores.{{ $i }}.comment" class="form-input" style="margin-top:6px;font-size:11.5px;" placeholder="Komentar (opsional)">
                        </div>
                    @endforeach

                    <div style="margin-top:14px;">
                        <label class="form-label">Keputusan</label>
                        <select wire:model="reviewDecision" class="form-input">
                            <option value="">-- Pilih Keputusan --</option>
                            @foreach ($this->decisionOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reviewDecision') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($reviewDecision === 'perpanjang')
                        <div style="margin-top:10px;">
                            <label class="form-label">Perpanjang berapa lama?</label>
                            <select wire:model="reviewExtensionMonths" class="form-input">
                                <option value="1">1 bulan</option>
                                <option value="2">2 bulan</option>
                                <option value="3">3 bulan</option>
                                <option value="6">6 bulan</option>
                                <option value="12">12 bulan</option>
                            </select>
                            <p style="font-size:11px;color:var(--text-faint);margin-top:4px;">Periode orientasi baru dibuat menyambung dari periode berjalan.</p>
                            @error('reviewExtensionMonths') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div style="margin-top:10px;">
                        <label class="form-label">Catatan Keputusan (wajib)</label>
                        <textarea wire:model="reviewNote" class="form-input" rows="2"></textarea>
                        @error('reviewNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                        <button type="button" wire:click="rejectStep" wire:confirm="Tolak pengajuan orientasi ini?" class="btn-ghost" style="color:#dc2626;">Tolak</button>
                        <button type="button" wire:click="submitReview" wire:confirm="Simpan penilaian & keputusan ini?" class="btn-primary">Simpan Penilaian</button>
                    </div>
                @else
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">Persetujuan Anda</p>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">Anda approver tahap ini. Penilaian skor diisi oleh approver terakhir (atasan langsung).</p>
                    <div>
                        <label class="form-label">Catatan (opsional, untuk penolakan)</label>
                        <textarea wire:model="rejectNote" class="form-input" rows="2"></textarea>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
                        <button type="button" wire:click="rejectStep" wire:confirm="Tolak pengajuan ini?" class="btn-ghost" style="color:#dc2626;">Tolak</button>
                        <button type="button" wire:click="approveStep" wire:confirm="Setujui & teruskan?" class="btn-primary">Setujui</button>
                    </div>
                @endif
            </div>
        @endif

        {{-- Timeline --}}
        <x-approval-history-list :histories="$r->approvalHistories()->orderBy('created_at')->get()" />
    </div>
</div>
