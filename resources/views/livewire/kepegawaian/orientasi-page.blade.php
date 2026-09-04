<div>
    <x-page-header title="Orientasi Saya" subtitle="Ajukan penilaian masa orientasi & unggah bukti kegiatan" />

    @php
        $current = $this->current;
        $statusLabels = [
            'draft' => ['Draf', 'var(--text-muted)', 'rgba(148,163,184,0.15)'],
            'menunggu_persetujuan' => ['Menunggu Persetujuan', '#b45309', 'rgba(245,158,11,0.12)'],
            'dinilai' => ['Sedang Dinilai', '#1d4ed8', 'rgba(37,99,235,0.12)'],
            'selesai' => ['Selesai', '#047C54', 'rgba(4,124,84,0.12)'],
            'dibatalkan' => ['Dibatalkan', '#dc2626', 'rgba(220,38,38,0.12)'],
        ];
        $decisionLabels = [
            'lulus' => ['Lulus', '#047C54'],
            'perpanjang' => ['Perpanjang Orientasi', '#b45309'],
            'tidak_lulus' => ['Tidak Lulus', '#dc2626'],
        ];
    @endphp

    @if (! $current)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 14px;">Anda belum mengajukan penilaian orientasi.</p>
            <button type="button" wire:click="startApplication" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Mulai Pengajuan Orientasi
            </button>
        </div>
    @else
        @php [$sLabel, $sColor, $sBg] = $statusLabels[$current->status] ?? [$current->status, 'var(--text-muted)', 'transparent']; @endphp
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;">
            <div style="margin-bottom:14px;">
                <span style="display:inline-block;padding:4px 12px;border-radius:99px;font-size:11.5px;font-weight:700;background:{{ $sBg }};color:{{ $sColor }};">{{ $sLabel }}</span>
            </div>

            @if ($current->status === 'selesai' && $current->decision)
                @php [$dLabel, $dColor] = $decisionLabels[$current->decision] ?? [$current->decision, 'var(--text-muted)']; @endphp
                <div style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 3px;">Keputusan Penilai</p>
                    <p style="font-size:14px;font-weight:700;color:{{ $dColor }};margin:0;">{{ $dLabel }}</p>
                    @if ($current->decision_note)
                        <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">{{ $current->decision_note }}</p>
                    @endif
                </div>
            @endif

            {{-- Bukti kegiatan --}}
            <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Bukti Kegiatan Orientasi</p>
            @if (empty($current->attachments))
                <p style="font-size:12px;color:var(--text-faint);margin:0 0 10px;">Belum ada bukti diunggah.</p>
            @else
                <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px;">
                    @foreach ($current->attachments as $i => $att)
                        <div style="display:flex;align-items:center;gap:8px;border:1px solid var(--border-soft);border-radius:8px;padding:7px 10px;">
                            <i class="fa-solid fa-paperclip" style="color:var(--text-faint);font-size:12px;"></i>
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($att['path']) }}" target="_blank" rel="noopener" style="flex:1;min-width:0;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $att['name'] ?? 'Lampiran' }}</a>
                            @if ($current->status === 'draft')
                                <button type="button" wire:click="removeBukti({{ $i }})" class="btn-ghost" style="padding:3px 7px;font-size:11px;color:#dc2626;"><i class="fa-solid fa-xmark"></i></button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($current->status === 'draft')
                <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;">Format: PDF atau PowerPoint (.pdf, .ppt, .pptx), maks 20 MB.</p>
                <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:14px;">
                    <input type="file" wire:model="buktiFile" class="form-input" style="flex:1;" accept=".pdf,.ppt,.pptx">
                    <button type="button" wire:click="uploadBukti" wire:loading.attr="disabled" wire:target="buktiFile,uploadBukti" class="btn-ghost" style="white-space:nowrap;"><i class="fa-solid fa-upload"></i> Unggah</button>
                </div>
                <div wire:loading wire:target="buktiFile" style="font-size:11px;color:var(--text-muted);margin-top:-8px;margin-bottom:10px;">Mengunggah...</div>
                @error('buktiFile') <p style="font-size:11px;color:#dc2626;margin-top:-6px;margin-bottom:10px;">{{ $message }}</p> @enderror

                <div style="margin-bottom:14px;">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea wire:model="note" class="form-input" rows="2" placeholder="Ringkasan kegiatan / hal yang ingin disampaikan ke penilai"></textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" wire:click="cancel" wire:confirm="Batalkan pengajuan ini?" class="btn-ghost" style="color:#dc2626;">Batalkan</button>
                    <button type="button" wire:click="submit" wire:confirm="Kirim pengajuan orientasi untuk dinilai atasan?" class="btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> Kirim Pengajuan
                    </button>
                </div>
            @elseif (in_array($current->status, ['menunggu_persetujuan', 'dinilai']))
                <div style="display:flex;justify-content:flex-end;">
                    <button type="button" wire:click="cancel" wire:confirm="Batalkan pengajuan ini?" class="btn-ghost" style="color:#dc2626;font-size:12px;">Batalkan Pengajuan</button>
                </div>
            @elseif ($this->canStartNew)
                <div style="display:flex;justify-content:flex-end;border-top:1px solid var(--border-soft);padding-top:12px;margin-top:4px;">
                    <button type="button" wire:click="startApplication" wire:confirm="Mulai pengajuan penilaian orientasi baru?" class="btn-primary" style="font-size:12.5px;">
                        <i class="fa-solid fa-plus"></i> Ajukan Lagi
                    </button>
                </div>
            @endif
        </div>

        {{-- Timeline pengajuan (inline, tidak ada halaman detail terpisah untuk pengaju) --}}
        @if ($current->status !== 'draft')
            <div style="margin-top:16px;">
                <x-approval-history-list :histories="$current->approvalHistories()->orderBy('created_at')->get()" />
            </div>
        @endif
    @endif

    {{-- Riwayat — disembunyikan selama masih ada pengajuan yang berjalan --}}
    @php
        $inProgress = $current && in_array($current->status, ['draft', 'menunggu_persetujuan', 'dinilai']);
        $past = $inProgress ? collect() : $this->submissions->filter(fn ($s) => $s->id !== optional($current)->id);
    @endphp
    @if ($past->isNotEmpty())
        <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:20px 0 8px;">Riwayat Pengajuan</p>
        <div class="pwa-list">
            @foreach ($past as $s)
                @php [$rl, $rc, $rb] = $statusLabels[$s->status] ?? [$s->status, 'var(--text-muted)', 'transparent']; @endphp
                <div class="pwa-list-row">
                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-user-graduate"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $s->submitted_at?->format('d M Y') ?? 'Draft' }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $s->decision ? ($decisionLabels[$s->decision][0] ?? $s->decision) : '—' }}</p>
                    </div>
                    <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:10.5px;font-weight:600;background:{{ $rb }};color:{{ $rc }};">{{ $rl }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
