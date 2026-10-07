<div>
<x-page-header title="Pengumuman" :back="route('dashboard')" />

<div class="card-surface accent accent-navy">
    <h2 style="font-size:18px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">📢 {{ $record->title }}</h2>
    <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 16px;">
        @if ($record->publisher) Oleh {{ $record->publisher->name }} · @endif
        {{ $record->published_at?->translatedFormat('j F Y H:i') }}
    </p>

    <div style="font-size:14px;line-height:1.65;color:var(--text-body);overflow-wrap:anywhere;">
        {!! \Filament\Forms\Components\RichEditor\RichContentRenderer::make($record->body)->toHtml() !!}
    </div>
</div>
</div>
