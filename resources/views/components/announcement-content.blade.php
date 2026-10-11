@props(['title', 'body', 'by' => null, 'at' => null])

<div class="card-surface accent accent-navy">
    <h2 style="font-size:18px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">📢 {{ $title }}</h2>
    <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 16px;">
        @if ($by) Oleh {{ $by }} · @endif
        {{ $at }}
    </p>

    <div class="[&_img]:max-w-full [&_img]:h-auto" style="font-size:14px;line-height:1.65;color:var(--text-body);overflow-wrap:anywhere;">
        {!! \Filament\Forms\Components\RichEditor\RichContentRenderer::make($body)->toHtml() !!}
    </div>
</div>
