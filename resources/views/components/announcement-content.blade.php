@props(['title', 'body', 'by' => null, 'at' => null])

<div class="card-surface accent accent-navy">
    <div style="display:flex;align-items:center;gap:12px;margin:0 0 6px;">
        <img src="{{ asset('assets/mascot/announcement.png') }}" alt="" aria-hidden="true" style="width:64px;height:64px;flex-shrink:0;object-fit:contain;">
        <h2 style="font-size:18px;font-weight:700;color:var(--text-heading);margin:0;min-width:0;overflow-wrap:anywhere;">{{ $title }}</h2>
    </div>
    <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 16px;">
        @if ($by) Oleh {{ $by }} · @endif
        {{ $at }}
    </p>

    <div class="[&_img]:max-w-full [&_img]:h-auto" style="font-size:14px;line-height:1.65;color:var(--text-body);overflow-wrap:anywhere;">
        {!! \Filament\Forms\Components\RichEditor\RichContentRenderer::make($body)->toHtml() !!}
    </div>
</div>
