<div>
<x-page-header title="Pengumuman" :back="route('dashboard')" />

<x-announcement-content :title="$record->title" :body="$record->body" :by="$record->publisher?->name" :at="$record->published_at?->translatedFormat('j F Y H:i')" />
</div>
