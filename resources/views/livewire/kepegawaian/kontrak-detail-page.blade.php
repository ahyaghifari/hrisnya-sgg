<div>
    <x-page-header title="Detail Pengajuan Perpanjangan Kontrak" />

    @php
        $r = $this->record;
        $decisionLabels = [
            'perpanjang' => ['Perpanjang Kontrak', '#047C54'],
            'tidak_perpanjang' => ['Tidak Diperpanjang', '#dc2626'],
        ];
        $statusLabels = [
            'draft' => ['Draf', 'var(--text-muted)'],
            'diajukan' => ['Diajukan', '#b45309'],
            'selesai' => ['Disetujui', '#047C54'],
            'ditolak' => ['Tidak Diperpanjang', '#dc2626'],
            'dibatalkan' => ['Dibatalkan', '#dc2626'],
        ];
        [$sLabel, $sColor] = $statusLabels[$r->status] ?? [$r->status, 'var(--text-muted)'];
        $currentContract = $r->user?->currentContract ?? $r->userContract;
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
            @if ($currentContract)
                <p style="font-size:12px;color:var(--text-body);margin:0 0 4px;">
                    <span style="color:var(--text-faint);">Kontrak berjalan:</span>
                    {{ $currentContract->contract_start_date?->format('d M Y') }} &ndash; {{ $currentContract->contract_end_date?->format('d M Y') }}
                </p>
            @endif
            @if ($r->alasan)
                <p style="font-size:12.5px;color:var(--text-body);margin:0;"><span style="color:var(--text-faint);">Alasan:</span> {{ $r->alasan }}</p>
            @endif
            @if ($r->usulan_durasi_bulan)
                <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0;">Usulan durasi karyawan: {{ $r->usulan_durasi_bulan }} bulan</p>
            @endif
            @if ($r->submitted_at)
                <p style="font-size:11px;color:var(--text-faint);margin:8px 0 0;">Diajukan {{ $r->submitted_at->translatedFormat('d F Y H:i') }}</p>
            @endif
        </div>

        {{-- Dokumen --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Dokumen Pendukung</p>
            @forelse ($r->documents as $doc)
                <div style="display:flex;align-items:center;gap:8px;border:1px solid var(--border-soft);border-radius:8px;padding:7px 10px;margin-bottom:6px;">
                    <i class="fa-solid fa-paperclip" style="color:var(--text-faint);font-size:12px;"></i>
                    <span style="flex:0 0 auto;font-size:11.5px;font-weight:600;color:var(--text-body);">{{ $doc->label }}</span>
                    <a href="{{ $doc->url }}" target="_blank" rel="noopener" style="flex:1;min-width:0;font-size:11.5px;">{{ $doc->original_name }}</a>
                </div>
            @empty
                <p style="font-size:12px;color:var(--text-faint);margin:0;">Tidak ada dokumen.</p>
            @endforelse
        </div>

        {{-- Keputusan (kalau sudah) --}}
        @if (in_array($r->status, ['selesai', 'ditolak']) && $r->decision)
            @php [$dLabel, $dColor] = $decisionLabels[$r->decision] ?? [$r->decision, 'var(--text-muted)']; @endphp
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 3px;">Keputusan Penilai — {{ $r->decidedBy?->name }}</p>
                <p style="font-size:15px;font-weight:700;color:{{ $dColor }};margin:0 0 4px;">{{ $dLabel }}</p>
                @if ($r->decision === 'perpanjang' && $r->newContract)
                    <p style="font-size:12.5px;color:var(--text-body);margin:0 0 4px;">Kontrak baru: <strong>{{ $r->newContract->contract_start_date?->format('d M Y') }}</strong> &ndash; <strong>{{ $r->newContract->contract_end_date?->format('d M Y') }}</strong> ({{ $r->decision_durasi_bulan }} bln)</p>
                @endif
                @if ($r->decision_note)<p style="font-size:12.5px;color:var(--text-body);margin:0;">{{ $r->decision_note }}</p>@endif
            </div>
        @endif

        {{-- FORM PENILAI --}}
        @if ($this->canReview)
            <div style="background:var(--surface);border:2px solid #1d4ed8;border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Keputusan Perpanjangan Kontrak</p>

                <div style="margin-bottom:10px;">
                    <label class="form-label">Keputusan</label>
                    <select wire:model.live="reviewDecision" class="form-input">
                        <option value="">-- Pilih Keputusan --</option>
                        @foreach ($this->decisionOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reviewDecision') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                @if ($reviewDecision === 'perpanjang')
                    <div style="margin-bottom:10px;max-width:220px;">
                        <label class="form-label">Durasi Perpanjangan (bulan)</label>
                        <input type="number" wire:model.live="reviewDurasi" class="form-input" min="1" max="24">
                        @error('reviewDurasi') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    @if ($this->preview)
                        <p style="font-size:12px;color:var(--text-body);margin:0 0 10px;background:var(--warn-soft-bg);border:1px solid var(--warn-soft-border);border-radius:8px;padding:8px 10px;">
                            Kontrak baru: <strong>{{ $this->preview[0]->format('d M Y') }}</strong> &ndash; <strong>{{ $this->preview[1]->format('d M Y') }}</strong>
                        </p>
                    @endif
                @endif

                <div style="margin-bottom:12px;">
                    <label class="form-label">Catatan Keputusan (wajib)</label>
                    <textarea wire:model="reviewNote" class="form-input" rows="2"></textarea>
                    @error('reviewNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div style="display:flex;justify-content:flex-end;">
                    <button type="button" wire:click="submitReview" wire:confirm="Simpan keputusan ini? (jika perpanjang, kontrak baru langsung dibuat)" class="btn-primary">Simpan Keputusan</button>
                </div>

                {{-- Kembalikan ke karyawan buat lengkapi dokumen --}}
                <div style="border-top:1px dashed var(--border);margin-top:16px;padding-top:12px;">
                    <label class="form-label">Minta Revisi Dokumen (opsional)</label>
                    <textarea wire:model="revisionNote" class="form-input" rows="2" placeholder="Jelaskan dokumen apa yang perlu diperbaiki/dilengkapi karyawan"></textarea>
                    @error('revisionNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                        <button type="button" wire:click="requestRevision" wire:confirm="Kembalikan pengajuan ke karyawan untuk revisi dokumen?" class="btn-ghost" style="font-size:12px;color:#b45309;">
                            <i class="fa-solid fa-rotate-left"></i> Kembalikan untuk Revisi
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
