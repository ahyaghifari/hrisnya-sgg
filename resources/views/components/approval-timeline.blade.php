@props(['timeline', 'title' => 'Status Persetujuan'])

<div class="timeline-card p-6 mb-6">
    <div class="tl-deco-1"></div><div class="tl-deco-2"></div><div class="tl-deco-3"></div>

    <div style="position:relative;z-index:1;">
    <h3 style="font-size:14px;font-weight:800;color:#422006;margin:0 0 20px;">{{ $title }}</h3>

    {{-- Vertikal di mobile (tidak perlu scroll horizontal buat lihat seluruh rantai approval
         di layar sempit), horizontal berdampingan di desktop (lebih lega, sekali pandang). --}}
    <div class="md:hidden">
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
                <div style="display:flex;gap:12px;">
                    <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                        <div style="width:32px;height:32px;border-radius:50%;background:#fff;border:2.5px solid {{ $accentColor }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            @if ($icon)
                                <i class="fa-solid {{ $icon }}" style="color:{{ $accentColor }};font-size:12px;"></i>
                            @endif
                        </div>
                        @unless ($loop->last)
                            <div style="width:2px;flex:1;min-height:24px;background:{{ in_array($node['state'], ['done', 'approved']) ? '#15803d' : '#E4D3AC' }};margin:4px 0;"></div>
                        @endunless
                    </div>
                    <div style="padding-bottom:20px;min-width:0;">
                        <p style="font-size:12.5px;font-weight:700;color:#374151;margin:0 0 2px;">{{ $node['actor'] ?? '—' }}</p>
                        <p style="font-size:11.5px;font-weight:600;margin:0 0 2px;color:{{ $accentColor }};">{{ $node['label'] }}</p>
                        <p style="font-size:11px;color:#64748b;margin:0;">{{ $node['date']?->translatedFormat('d M Y') ?? ($node['state'] === 'current' ? 'Sedang diproses' : 'Menunggu') }}</p>
                    </div>
                </div>
            @endforeach
    </div>

    <div class="hidden md:block">
        <div style="display:flex;overflow-x:auto;padding-bottom:4px;">
            @foreach ($timeline as $node)
                <div style="flex:1;min-width:130px;position:relative;text-align:center;">
                    @if (!$loop->first)
                        <div style="position:absolute;top:18px;left:-50%;width:100%;height:2px;background:{{ in_array($node['state'], ['done','approved']) ? '#15803d' : '#E4D3AC' }};z-index:0;"></div>
                    @endif

                    <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;">
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
                        {{-- Cincin & ikon berwarna sebagai penanda status, tanpa shadow -- kontras jadi mengandalkan warna, bukan efek raised --}}
                        <div style="width:36px;height:36px;border-radius:50%;background:#fff;border:2.5px solid {{ $accentColor }};display:flex;align-items:center;justify-content:center;">
                            @if ($icon)
                                <i class="fa-solid {{ $icon }}" style="color:{{ $accentColor }};font-size:13px;"></i>
                            @endif
                        </div>

                        <p style="font-size:12px;font-weight:700;color:#374151;margin:10px 0 2px;">{{ $node['actor'] ?? '—' }}</p>
                        <p style="font-size:11px;color:#64748b;margin:0 0 4px;">{{ $node['date']?->translatedFormat('d M Y') ?? ($node['state'] === 'current' ? 'Sedang diproses' : 'Menunggu') }}</p>
                        <p style="font-size:11.5px;font-weight:600;margin:0;color:{{ $accentColor }};">{{ $node['label'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @isset($slot)
        @if (trim($slot))
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #F0DBA3;">
                {{ $slot }}
            </div>
        @endif
    @endisset
    </div>
</div>
