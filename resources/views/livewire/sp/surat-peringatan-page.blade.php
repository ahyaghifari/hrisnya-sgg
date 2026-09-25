<div>
    <x-page-header title="Surat Peringatan" size="lg" />

    @php
        $levelColors = [
            'sp1' => ['background' => '#fef9c3', 'color' => '#a16207'],
            'sp2' => ['background' => '#ffedd5', 'color' => '#c2410c'],
            'sp3' => ['background' => '#fee2e2', 'color' => '#b91c1c'],
        ];
    @endphp

    <div class="pwa-list">
        @forelse ($sps as $sp)
            @php
                $isActive = ! $sp->isCancelled() && $sp->start_date->lte(today()) && ($sp->end_date === null || $sp->end_date->gte(today()));
                $levelColor = $levelColors[$sp->sp_level->value] ?? ['background' => '#f1f5f9', 'color' => '#64748b'];
            @endphp
            <a href="{{ route('sp.saya.show', $sp) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">
                        <span class="badge" style="background:{{ $levelColor['background'] }};color:{{ $levelColor['color'] }};margin-right:6px;">{{ $sp->sp_level->getLabel() }}</span>
                        @if ($sp->nomor)
                            No. {{ $sp->nomor }}
                        @endif
                    </p>
                    <span style="font-size:11.5px;color:var(--text-muted);">
                        {{ $sp->start_date->translatedFormat('d M Y') }}
                        &middot;
                        {{ $sp->end_date ? $sp->end_date->translatedFormat('d M Y') : 'Tidak pernah berakhir' }}
                        &middot;
                        {{ $sp->isCancelled() ? 'Dibatalkan' : ($isActive ? 'Aktif' : 'Expired') }}
                    </span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada SP</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Riwayat Surat Peringatan untuk Anda akan muncul di sini.</span>
                </div>
            </div>
        @endforelse
    </div>
</div>
