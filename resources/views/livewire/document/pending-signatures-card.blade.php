<div @if ($this->pending->isEmpty()) style="display:none;" @endif>
@if ($this->pending->isNotEmpty())
<div class="pwa-list" style="grid-column:1/-1;padding-bottom:16px;margin-top:12px;">
    <div class="pwa-card-header pwa-card-header-ink" style="margin-bottom:14px;">
        <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
        <div class="pwa-card-header-content">
            <span class="pwa-section-title"><i class="fa-solid fa-signature" style="font-size:12px;margin-right:4px;"></i> Dokumen Menunggu Penandatanganan</span>
            <span style="background:rgba(255,255,255,0.92);color:#111827;font-size:12px;font-weight:800;min-width:24px;height:24px;padding:0 8px;border-radius:99px;display:flex;align-items:center;justify-content:center;">{{ $this->pending->count() }}</span>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:10px;padding:0 16px;">
        @foreach ($this->pending as $doc)
            @php $roleLabel = $doc->documentable->expectedSignerRoles()[$doc->pendingRoleFor(auth()->user())] ?? null; @endphp
            <div style="background:var(--surface-alt);border-radius:12px;padding:12px 14px;display:flex;align-items:center;gap:12px;">
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $doc->title }}</p>
                    <p style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                        {{ \App\Enum\DocumentType::from($doc->document_type)->label() }} &middot; No. {{ $doc->document_number ?: '-' }}
                        @if ($roleLabel) &middot; peran Anda: {{ $roleLabel }} @endif
                    </p>
                </div>
                <a href="{{ $this->detailUrl($doc) }}" wire:navigate class="btn-ink" style="padding:7px 14px;font-size:12px;flex-shrink:0;">
                    Lihat Detail <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endif
</div>
