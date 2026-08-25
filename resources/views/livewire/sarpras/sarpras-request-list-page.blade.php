<div>
    <div class="page-hero" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Sarana &amp; Prasarana</h1>
                <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Permohonan perbaikan &amp; pengadaan sarana prasarana</p>
            </div>
            @can(\App\Enum\CustomPermissionEnum::AJUKAN_SARANA_PRASARANA->value)
                <a href="{{ route('sarana-prasarana.ajukan') }}" wire:navigate style="white-space:nowrap;display:inline-flex;align-items:center;gap:8px;background:#fff;color:#042c6c;font-weight:700;font-size:13px;padding:10px 20px;border-radius:12px;text-decoration:none;">
                    <i class="fa-solid fa-plus"></i> Ajukan Permohonan
                </a>
            @endcan
        </div>
    </div>

    @php
        $statusBadge = fn (string $status) => match ($status) {
            'disetujui', 'disetujui_sebagian', 'selesai' => ['badge-approved', match ($status) {
                'disetujui' => 'Disetujui', 'disetujui_sebagian' => 'Disetujui Sebagian', default => 'Selesai',
            }],
            'ditolak' => ['badge-rejected', 'Ditolak'],
            'diputuskan_sebagian' => ['badge-pending', 'Diputuskan Sebagian'],
            'menunggu_direktur' => ['badge-pending', 'Menunggu Direktur'],
            default => ['badge-pending', 'Diajukan'],
        };
    @endphp

    @foreach ([['label' => 'Menunggu Tindakan Saya', 'items' => $this->queue, 'empty' => 'Tidak ada permohonan yang menunggu tindakan Anda.'], ['label' => 'Permohonan Saya', 'items' => $this->mine, 'empty' => 'Anda belum mengajukan permohonan.']] as $section)
        <div style="margin-bottom:24px;">
            <h2 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">{{ $section['label'] }}</h2>

            @if ($section['items']->isEmpty())
                <div class="pwa-list" style="padding:24px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">{{ $section['empty'] }}</p>
                </div>
            @else
                <div class="pwa-list">
                    @foreach ($section['items'] as $item)
                        @php [$color, $label] = $statusBadge($item->status); @endphp
                        <a href="{{ route('sarana-prasarana.show', $item) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                            <div style="flex:1;min-width:0;">
                                <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $item->kode }} &middot; {{ $item->perihal }}</p>
                                <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                    {{ $item->unit }} &middot; {{ ucfirst($item->jenis) }} &middot; {{ $item->user->name }}
                                </p>
                                <span class="badge {{ $color }}" style="margin-top:4px;display:inline-block;">{{ $label }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
