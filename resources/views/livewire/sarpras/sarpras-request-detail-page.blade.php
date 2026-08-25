@php
    $r = $sarprasRequest;
    $statusBadge = match ($r->status) {
        'disetujui', 'disetujui_sebagian', 'selesai' => ['badge-approved', match ($r->status) {
            'disetujui' => 'Disetujui', 'disetujui_sebagian' => 'Disetujui Sebagian', default => 'Selesai',
        }],
        'ditolak' => ['badge-rejected', 'Ditolak'],
        'diputuskan_sebagian' => ['badge-pending', 'Diputuskan Sebagian'],
        'menunggu_direktur' => ['badge-pending', 'Menunggu Direktur'],
        default => ['badge-pending', 'Diajukan'],
    };
    $itemStatusBadge = fn (string $s) => match ($s) {
        'disetujui' => ['badge-approved', 'Disetujui'],
        'ditolak' => ['badge-rejected', 'Ditolak'],
        default => ['badge-pending', 'Menunggu'],
    };
    $peranLabel = ['unit' => 'Unit', 'kabid' => 'Kabid Yanmed', 'direktur' => 'Direktur'];
@endphp

<div>
    <div class="page-hero" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
            <div>
                <p style="font-size:12px;color:rgba(255,255,255,0.75);margin:0 0 4px;">{{ $r->kode }}</p>
                <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">{{ $r->perihal }}</h1>
                <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $r->unit }} &middot; {{ ucfirst($r->jenis) }} &middot; Diajukan {{ $r->user->name }}</p>
            </div>
            <span class="badge {{ $statusBadge[0] }}" style="font-size:12px;">{{ $statusBadge[1] }}</span>
        </div>
    </div>

    <x-approval-timeline :timeline="$this->timeline" />

    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;margin-top:16px;">
        <h2 style="font-size:14px;font-weight:700;margin:0 0 8px;">Uraian</h2>
        <p style="font-size:13px;color:var(--text-body);white-space:pre-line;">{{ $r->uraian }}</p>
    </div>

    {{-- Rincian item + form keputusan Direktur --}}
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;margin-top:16px;">
        <h2 style="font-size:14px;font-weight:700;margin:0 0 12px;">Rincian Item</h2>

        @foreach ($r->items as $item)
            @php [$iColor, $iLabel] = $itemStatusBadge($item->status); @endphp
            <div wire:key="item-{{ $item->id }}" style="border:1.5px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                    <div>
                        <p style="font-size:13px;font-weight:600;margin:0;">{{ $item->nama }}</p>
                        @if ($item->spesifikasi)
                            <p style="font-size:12px;color:var(--text-muted);margin:2px 0 0;">{{ $item->spesifikasi }}</p>
                        @endif
                        <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0;">
                            Diminta: {{ $item->jumlah }} {{ $item->satuan }}
                            @if ($item->harga_satuan)
                                &middot; Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}/{{ $item->satuan }}
                            @endif
                        </p>
                    </div>
                    <span class="badge {{ $iColor }}">{{ $iLabel }}</span>
                </div>

                @if ($item->status !== 'menunggu')
                    <div style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--border);font-size:12px;color:var(--text-muted);">
                        @if ($item->status === 'disetujui')
                            Disetujui {{ $item->jumlah_disetujui }} {{ $item->satuan }}
                        @endif
                        @if ($item->catatan_direktur)
                            <br>Catatan: {{ $item->catatan_direktur }}
                        @endif
                    </div>
                @elseif ($this->canPutuskan)
                    <div style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--border);display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;align-items:end;">
                        <div>
                            <label class="form-label">Keputusan</label>
                            <select wire:model="itemDecisions.{{ $item->id }}.keputusan" class="form-input">
                                <option value="disetujui">Setujui</option>
                                <option value="ditolak">Tolak</option>
                            </select>
                        </div>
                        @if (($itemDecisions[$item->id]['keputusan'] ?? 'disetujui') === 'disetujui')
                            <div>
                                <label class="form-label">Jumlah Disetujui</label>
                                <input type="number" min="1" max="{{ $item->jumlah }}" wire:model="itemDecisions.{{ $item->id }}.jumlah_disetujui" class="form-input">
                            </div>
                        @endif
                        <div style="grid-column:1/-1;">
                            <label class="form-label">Catatan (opsional)</label>
                            <input type="text" wire:model="itemDecisions.{{ $item->id }}.catatan" class="form-input">
                        </div>
                        <div style="grid-column:1/-1;">
                            <button type="button" wire:click="putuskanItem('{{ $item->id }}')" class="btn-primary btn-sm">
                                Simpan Keputusan
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Aksi Kabid --}}
    @if ($this->canTeruskan)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;margin-top:16px;">
            <h2 style="font-size:14px;font-weight:700;margin:0 0 12px;">Teruskan ke Direktur</h2>
            <label class="form-label">Catatan (opsional)</label>
            <textarea wire:model="catatanKabid" class="form-input" rows="2"></textarea>
            <div style="margin-top:12px;">
                <button type="button" wire:click="teruskan" class="btn-primary">
                    <i class="fa-solid fa-share"></i> Teruskan
                </button>
            </div>
        </div>
    @endif

    {{-- Aksi Unit --}}
    @if ($this->canSelesaikan)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;margin-top:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <p style="font-size:13px;color:var(--text-body);margin:0;">Sudah diselesaikan pengerjaan/pengadaannya?</p>
            <button type="button" wire:click="selesaikan" wire:confirm="Tandai permohonan ini selesai?" class="btn-success">
                <i class="fa-solid fa-check"></i> Tandai Selesai
            </button>
        </div>
    @endif

    {{-- Lampiran --}}
    @if ($r->attachments->isNotEmpty())
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;margin-top:16px;">
            <h2 style="font-size:14px;font-weight:700;margin:0 0 12px;">Lampiran</h2>
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach ($r->attachments as $file)
                    <a href="{{ $this->getAttachmentUrl($file->nama_simpan) }}" target="_blank" style="font-size:13px;color:#042c6c;text-decoration:none;display:flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-paperclip"></i> {{ $file->nama_asli }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tanda tangan --}}
    @if ($r->signatures->isNotEmpty())
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;margin-top:16px;">
            <h2 style="font-size:14px;font-weight:700;margin:0 0 12px;">Tanda Tangan Elektronik</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                @foreach ($r->signatures as $ttd)
                    <div style="border:1.5px solid var(--border);border-radius:10px;padding:12px;">
                        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--text-faint);margin:0 0 4px;">{{ $peranLabel[$ttd->peran] ?? $ttd->peran }}</p>
                        <p style="font-size:13px;font-weight:600;margin:0;">{{ $ttd->nama }}</p>
                        @if ($ttd->jabatan)
                            <p style="font-size:12px;color:var(--text-muted);margin:2px 0 0;">{{ $ttd->jabatan }}</p>
                        @endif
                        <p style="font-family:monospace;font-size:11px;color:var(--text-faint);margin:6px 0 0;">{{ $ttd->kode }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
