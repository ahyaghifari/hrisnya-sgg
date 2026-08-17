<div @if ($this->pendingResponses->isEmpty()) style="display:none;" @endif>
@if ($this->pendingResponses->isNotEmpty())
<div class="pwa-list" style="grid-column:1/-1;padding-bottom:16px;">
    <div class="pwa-card-header pwa-card-header-sky" style="margin-bottom:14px;">
        <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
        <div class="pwa-card-header-content">
            <span class="pwa-section-title"><i class="fa-solid fa-right-left" style="font-size:12px;margin-right:4px;"></i> Tanggapan Tukar Shift</span>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:10px;padding:0 16px;">
        @foreach ($this->pendingResponses as $request)
            <div style="background:var(--surface-alt);border-radius:12px;padding:12px 14px;">
                <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $request->requester?->name }}</p>
                <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                    Mau menukar jadwal shift-nya dengan Anda
                    @if ($request->reqSchedule)
                        pada <strong>{{ $request->reqSchedule->date->translatedFormat('d M Y') }}</strong>
                        ({{ $request->reqSchedule->shiftType?->name }})
                    @endif
                </p>
                <div style="display:flex;gap:8px;margin-top:10px;">
                    <button type="button" wire:click="respond('{{ $request->id }}', 'accepted')" class="btn-primary" style="flex:1;justify-content:center;padding:7px 10px;font-size:12px;">
                        <i class="fa-solid fa-check"></i> Setuju
                    </button>
                    <button type="button" wire:click="respond('{{ $request->id }}', 'declined')" class="btn-ghost" style="flex:1;justify-content:center;padding:7px 10px;font-size:12px;color:var(--danger-text);border-color:var(--danger-soft-border);">
                        <i class="fa-solid fa-xmark"></i> Tolak
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif
</div>
