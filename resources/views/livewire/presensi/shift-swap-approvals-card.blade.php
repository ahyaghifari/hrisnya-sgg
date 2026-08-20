<div @if ($this->pending->isEmpty()) style="display:none;" @endif>
@if ($this->pending->isNotEmpty())
<div class="pwa-list" style="grid-column:1/-1;padding-bottom:16px;">
    <div class="pwa-card-header pwa-card-header-amber" style="margin-bottom:14px;">
        <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
        <div class="pwa-card-header-content">
            <span class="pwa-section-title"><i class="fa-solid fa-right-left" style="font-size:12px;margin-right:4px;"></i> Persetujuan Tukar Shift</span>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:10px;padding:0 16px;">
        @foreach ($this->pending as $record)
            <div style="background:var(--surface-alt);border-radius:12px;padding:12px 14px;">
                <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $record->requester?->name }}</p>
                <p style="font-size:12px;color:var(--text-muted);margin-top:3px;">{{ $this->keterangan($record) }}</p>

                <div style="display:flex;gap:6px;align-items:flex-start;margin-top:8px;padding:8px 10px;background:var(--info-soft-bg);border-radius:8px;">
                    <i class="fa-solid fa-circle-info" style="font-size:11px;color:var(--info-soft-text);margin-top:2px;flex-shrink:0;"></i>
                    <p style="font-size:11px;color:var(--info-soft-text);margin:0;line-height:1.5;">{!! $this->efek($record) !!}</p>
                </div>

                <div style="display:flex;gap:6px;margin-top:10px;">
                    <button type="button" wire:click="approve('{{ $record->id }}')" class="btn-primary" style="flex:1;justify-content:center;padding:5px 8px;font-size:11px;">
                        <i class="fa-solid fa-check"></i> Setuju
                    </button>
                    <button type="button" wire:click="reject('{{ $record->id }}')" class="btn-ghost" style="flex:1;justify-content:center;padding:5px 8px;font-size:11px;color:var(--danger-text);border-color:var(--danger-soft-border);">
                        <i class="fa-solid fa-xmark"></i> Tolak
                    </button>
                    <a href="{{ route('tukar-shift.show', ['record' => $record]) }}" wire:navigate class="btn-ghost" style="flex:1;justify-content:center;padding:5px 8px;font-size:11px;text-align:center;">
                        <i class="fa-solid fa-arrow-right"></i> Detail
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif
</div>
