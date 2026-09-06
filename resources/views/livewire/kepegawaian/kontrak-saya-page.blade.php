<div>
    <x-page-header title="Perpanjangan Kontrak Saya" subtitle="Ajukan perpanjangan kontrak & unggah dokumen pendukung" />

    @php
        $current = $this->current;
        $contract = $this->contract;
        $windowOpensAt = $this->windowOpensAt;
        $statusLabels = [
            'draft' => ['Draf', 'var(--text-muted)', 'rgba(148,163,184,0.15)'],
            'diajukan' => ['Diajukan', '#b45309', 'rgba(245,158,11,0.12)'],
            'selesai' => ['Disetujui', '#047C54', 'rgba(4,124,84,0.12)'],
            'ditolak' => ['Tidak Diperpanjang', '#dc2626', 'rgba(220,38,38,0.12)'],
            'dibatalkan' => ['Dibatalkan', '#dc2626', 'rgba(220,38,38,0.12)'],
        ];
        $decisionLabels = [
            'perpanjang' => ['Perpanjang Kontrak', '#047C54'],
            'tidak_perpanjang' => ['Tidak Diperpanjang', '#dc2626'],
        ];
    @endphp

    @if ($contract)
        <div style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:6px 18px;align-items:center;">
            <span style="font-size:11.5px;font-weight:700;color:var(--info-soft-text);text-transform:uppercase;letter-spacing:.04em;">Kontrak Berjalan</span>
            <span style="font-size:13px;color:var(--text-body);">{{ $contract->contract_start_date?->format('d M Y') }} &ndash; {{ $contract->contract_end_date?->format('d M Y') }}</span>
            @php $sisa = $contract->contract_end_date ? now()->startOfDay()->diffInDays($contract->contract_end_date, false) : null; @endphp
            @if ($sisa !== null)
                <span style="font-size:12px;font-weight:600;color:{{ $sisa < 0 ? 'var(--danger-text)' : ($sisa <= 30 ? '#b45309' : 'var(--text-muted)') }};">
                    {{ $sisa < 0 ? 'Berakhir ' . abs($sisa) . ' hari lalu' : $sisa . ' hari lagi' }}
                </span>
            @endif
        </div>
    @else
        <div style="background:var(--warn-soft-bg);border:1px solid var(--warn-soft-border);border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:12.5px;color:var(--warn-soft-text);">
            Kontrak berjalanmu belum tercatat. Pengajuan perpanjangan belum bisa dibuat — hubungi HR/HC.
        </div>
    @endif

    @if (! $current)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 14px;">Belum ada pengajuan perpanjangan kontrak.</p>
            <button type="button" wire:click="startApplication" class="btn-primary" @disabled(! $this->canStartNew)>
                <i class="fa-solid fa-plus"></i> Mulai Pengajuan Perpanjangan
            </button>
            @if ($contract && ! $this->canStartNew && $windowOpensAt)
                <p style="font-size:11.5px;color:#b45309;margin:10px 0 0;">Pengajuan dibuka mulai {{ $windowOpensAt->format('d M Y') }} ({{ $this->leadDays }} hari sebelum kontrak berakhir).</p>
            @endif
        </div>
    @else
        @php [$sLabel, $sColor, $sBg] = $statusLabels[$current->status] ?? [$current->status, 'var(--text-muted)', 'transparent']; @endphp
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;">
            <div style="margin-bottom:14px;">
                <span style="display:inline-block;padding:4px 12px;border-radius:99px;font-size:11.5px;font-weight:700;background:{{ $sBg }};color:{{ $sColor }};">{{ $sLabel }}</span>
            </div>

            @if ($current->status === 'draft' && $current->decision_note)
                <div style="border:1px solid var(--warn-soft-border);background:var(--warn-soft-bg);border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                    <p style="font-size:12px;font-weight:700;color:var(--warn-soft-text);margin:0 0 3px;">Penilai meminta revisi dokumen</p>
                    <p style="font-size:12.5px;color:var(--text-body);margin:0;">{{ $current->decision_note }}</p>
                </div>
            @endif

            @if (in_array($current->status, ['selesai', 'ditolak']) && $current->decision)
                @php [$dLabel, $dColor] = $decisionLabels[$current->decision] ?? [$current->decision, 'var(--text-muted)']; @endphp
                <div style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 3px;">Keputusan Penilai</p>
                    <p style="font-size:14px;font-weight:700;color:{{ $dColor }};margin:0;">{{ $dLabel }}</p>
                    @if ($current->decision === 'perpanjang' && $current->newContract)
                        <p style="font-size:12px;color:var(--text-body);margin:6px 0 0;">Kontrak baru: {{ $current->newContract->contract_start_date?->format('d M Y') }} &ndash; {{ $current->newContract->contract_end_date?->format('d M Y') }} ({{ $current->decision_durasi_bulan }} bln)</p>
                    @endif
                    @if ($current->decision_note)
                        <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">{{ $current->decision_note }}</p>
                    @endif
                </div>
            @endif

            {{-- Alasan + usulan durasi (form saat draft, read-only lain) --}}
            @if ($current->status === 'draft')
                <div style="margin-bottom:14px;">
                    <label class="form-label">Alasan Perpanjangan (wajib)</label>
                    <textarea wire:model="alasan" class="form-input" rows="2" placeholder="Alasan mengajukan perpanjangan"></textarea>
                </div>
                <div style="margin-bottom:16px;max-width:220px;">
                    <label class="form-label">Usulan Durasi (bulan, opsional)</label>
                    <input type="number" wire:model="usulanDurasi" class="form-input" min="1" max="24" placeholder="mis. 12">
                    <p style="font-size:10.5px;color:var(--text-faint);margin:3px 0 0;">Durasi final ditetapkan penilai.</p>
                </div>
            @else
                @if ($current->alasan)
                    <p style="font-size:12.5px;color:var(--text-body);margin:0 0 4px;"><span style="color:var(--text-faint);">Alasan:</span> {{ $current->alasan }}</p>
                @endif
                @if ($current->usulan_durasi_bulan)
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">Usulan durasi: {{ $current->usulan_durasi_bulan }} bulan</p>
                @endif
            @endif

            {{-- Dokumen --}}
            <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:12px 0 8px;">Dokumen Pendukung</p>
            @php $uploaded = $current->documents->keyBy('label'); @endphp
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px;">
                @foreach ($this->requiredDocs as $doc)
                    @php $file = $uploaded->get($doc['label']); @endphp
                    <div style="display:flex;align-items:center;gap:8px;border:1px solid var(--border-soft);border-radius:8px;padding:7px 10px;">
                        <i class="fa-solid {{ $file ? 'fa-circle-check' : 'fa-circle' }}" style="color:{{ $file ? '#047C54' : 'var(--text-faint)' }};font-size:12px;"></i>
                        <span style="flex:0 0 auto;font-size:12px;font-weight:600;color:var(--text-body);">{{ $doc['label'] }}@if ($doc['required'])<span style="color:#dc2626;">*</span>@endif</span>
                        @if ($file)
                            <a href="{{ $file->url }}" target="_blank" rel="noopener" style="flex:1;min-width:0;font-size:11.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $file->original_name }}</a>
                            @if ($current->status === 'draft')
                                <button type="button" wire:click="removeDoc('{{ $file->id }}')" class="btn-ghost" style="padding:3px 7px;font-size:11px;color:#dc2626;"><i class="fa-solid fa-xmark"></i></button>
                            @endif
                        @else
                            <span style="flex:1;font-size:11px;color:var(--text-faint);">Belum diunggah</span>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($current->status === 'draft')
                <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px;">
                    <select wire:model="docLabel" class="form-input" style="max-width:220px;">
                        <option value="">— pilih jenis dokumen —</option>
                        @foreach ($this->requiredDocs as $doc)<option value="{{ $doc['label'] }}">{{ $doc['label'] }}</option>@endforeach
                    </select>
                    <input type="file" wire:model="docFile" class="form-input" style="flex:1;min-width:160px;" accept=".pdf,.ppt,.pptx,.jpg,.jpeg,.png">
                    <button type="button" wire:click="uploadDoc" wire:loading.attr="disabled" wire:target="docFile,uploadDoc" class="btn-ghost" style="white-space:nowrap;"><i class="fa-solid fa-upload"></i> Unggah</button>
                </div>
                <div wire:loading wire:target="docFile" style="font-size:11px;color:var(--text-muted);margin-top:-8px;margin-bottom:10px;">Mengunggah...</div>
                @error('docFile') <p style="font-size:11px;color:#dc2626;margin-top:-6px;margin-bottom:8px;">{{ $message }}</p> @enderror
                @error('docLabel') <p style="font-size:11px;color:#dc2626;margin-top:-6px;margin-bottom:8px;">{{ $message }}</p> @enderror

                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" wire:click="cancel" wire:confirm="Batalkan pengajuan ini?" class="btn-ghost" style="color:#dc2626;">Batalkan</button>
                    <button type="button" wire:click="submit" wire:confirm="Kirim pengajuan perpanjangan kontrak?" class="btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> Kirim Pengajuan
                    </button>
                </div>
            @elseif ($current->status === 'diajukan')
                <div style="display:flex;justify-content:flex-end;">
                    <button type="button" wire:click="cancel" wire:confirm="Batalkan pengajuan ini?" class="btn-ghost" style="color:#dc2626;font-size:12px;">Batalkan Pengajuan</button>
                </div>
            @elseif ($this->canReopen)
                <div style="display:flex;justify-content:flex-end;border-top:1px solid var(--border-soft);padding-top:12px;margin-top:4px;">
                    <button type="button" wire:click="reopenApplication" wire:confirm="Buka lagi pengajuan ini?" class="btn-primary" style="font-size:12.5px;">
                        <i class="fa-solid fa-rotate-left"></i> Ajukan Ulang
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Riwayat pengajuan lama --}}
    @php $past = $this->submissions->filter(fn ($s) => $s->id !== optional($current)->id)->values(); @endphp
    @if ($past->isNotEmpty())
        <details style="margin-top:20px;">
            <summary style="cursor:pointer;font-size:11.5px;font-weight:600;color:var(--text-muted);padding:4px 0;">Riwayat pengajuan lama ({{ $past->count() }})</summary>
            <div class="pwa-list" style="margin-top:6px;">
                @foreach ($past as $s)
                    @php [$rl, $rc, $rb] = $statusLabels[$s->status] ?? [$s->status, 'var(--text-muted)', 'transparent']; @endphp
                    <div class="pwa-list-row">
                        <div class="pwa-tile-icon neutral"><i class="fa-solid fa-file-signature"></i></div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $s->submitted_at?->format('d M Y') ?? 'Draft' }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $s->decision ? ($decisionLabels[$s->decision][0] ?? $s->decision) : '—' }}</p>
                        </div>
                        <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:10.5px;font-weight:600;background:{{ $rb }};color:{{ $rc }};">{{ $rl }}</span>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>
