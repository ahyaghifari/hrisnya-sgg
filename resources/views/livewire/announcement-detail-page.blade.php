<div>
<x-page-header title="Pengumuman" :back="route('dashboard')" />

<x-announcement-content :title="$record->title" :body="$record->body" :by="$record->publisher?->name" :at="$record->published_at?->translatedFormat('j F Y H:i')" />

<div class="card-surface" style="margin-top:16px;">
    <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Komentar ({{ $comments->count() }})</h3>

    @foreach ($comments as $c)
        <div style="padding:10px 0;border-top:1px solid var(--border, #e5e7eb);">
            <div style="font-size:12.5px;color:var(--text-muted);"><strong style="color:var(--text-heading);">{{ $c->user?->name ?? '-' }}</strong> · {{ $c->created_at->translatedFormat('j M Y H:i') }}</div>
            <div style="font-size:14px;line-height:1.55;color:var(--text-body);white-space:pre-line;overflow-wrap:anywhere;">{{ $c->body }}</div>
        </div>
    @endforeach

    @if ($comments->contains('user_id', auth()->id()))
        <p style="font-size:12.5px;color:var(--text-muted);margin:12px 0 0;">Kamu sudah berkomentar. Satu komentar per pengumuman.</p>
    @else
        <form wire:submit="addComment" style="margin-top:12px;">
            <textarea class="form-input" wire:model="comment" rows="3" maxlength="1000" placeholder="Tulis komentar (hanya bisa satu kali)"></textarea>
            @error('comment') <p style="color:#dc2626;font-size:12.5px;margin:4px 0 0;">{{ $message }}</p> @enderror
            <button type="submit" class="btn-primary" style="margin-top:8px;" wire:loading.attr="disabled">Kirim Komentar</button>
        </form>
    @endif
</div>
</div>
