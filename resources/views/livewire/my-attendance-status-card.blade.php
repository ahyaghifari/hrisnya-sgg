<div>
@if ($request)
    @php
        $title = 'Status Pengajuan ' . (['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$request->type] ?? $request->type) . ' Anda';
    @endphp
    <div class="timeline-card p-4" style="position:relative;">
        <div class="tl-deco-1"></div><div class="tl-deco-2"></div>

        <div style="position:relative;z-index:1;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            {{-- Kiri: judul + ringkasan tumpuk vertikal, bukan lagi header lebar sendiri di atas timeline --}}
            <div style="flex-shrink:0;min-width:160px;">
                <h3 style="font-size:13px;font-weight:800;color:#422006;margin:0 0 3px;">{{ $title }}</h3>
                <p style="font-size:11.5px;color:#7c5e24;margin:0 0 10px;">
                    <span style="font-weight:700;">{{ $summary['type_name'] }}</span>
                    &nbsp;&middot;&nbsp; {{ $summary['date_label'] }}
                </p>
                <a href="{{ route('my-attendance-requests.show', ['record' => $request->id]) }}" wire:navigate class="btn-primary" style="padding:8px 14px;font-size:12px;text-decoration:none;">
                    Lihat Detail <i class="fa-solid fa-arrow-right text-xs"></i>
                </a>
            </div>

            {{-- Kanan: timeline lengkap (ikon, nama, tanggal, label per step) seperti semula —
            cuma posisinya sekarang di samping, bukan lagi di atas ringkasan. --}}
            <div class="tl-scroll" style="flex:1;min-width:0;overflow-x:auto;">
                <div style="display:flex;padding-bottom:4px;">
                    @foreach ($timeline as $node)
                        @php
                            $accentColor = match ($node['state']) {
                                'done', 'approved' => '#15803d',
                                'current' => '#042c6c',
                                'rejected' => '#b91c1c',
                                'cancelled' => '#475569',
                                default => '#a16207',
                            };
                            $icon = match ($node['state']) {
                                'done', 'approved' => 'fa-check',
                                'current' => 'fa-hourglass-half',
                                'rejected' => 'fa-xmark',
                                'cancelled' => 'fa-ban',
                                default => null,
                            };
                        @endphp
                        <div style="flex:1;min-width:120px;position:relative;text-align:center;">
                            @if (!$loop->first)
                                <div style="position:absolute;top:16px;left:-50%;width:100%;height:2px;background:{{ in_array($node['state'], ['done','approved']) ? '#15803d' : '#E4D3AC' }};z-index:0;"></div>
                            @endif

                            <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;">
                                <div style="width:32px;height:32px;border-radius:50%;background:#fff;border:2.5px solid {{ $accentColor }};display:flex;align-items:center;justify-content:center;">
                                    @if ($icon)
                                        <i class="fa-solid {{ $icon }}" style="color:{{ $accentColor }};font-size:12px;"></i>
                                    @endif
                                </div>

                                <p style="font-size:11.5px;font-weight:700;color:#374151;margin:8px 0 2px;">{{ $node['actor'] ?? '—' }}</p>
                                <p style="font-size:10.5px;color:#64748b;margin:0 0 3px;">{{ $node['date']?->translatedFormat('d M Y') ?? ($node['state'] === 'current' ? 'Sedang diproses' : 'Menunggu') }}</p>
                                <p style="font-size:11px;font-weight:600;margin:0;color:{{ $accentColor }};">{{ $node['label'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
</div>
