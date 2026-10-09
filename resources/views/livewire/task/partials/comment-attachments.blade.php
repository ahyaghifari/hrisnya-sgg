{{-- Lampiran yang dikirim lewat komentar: foto jadi thumbnail, file lain jadi chip. Lampiran yang sama juga ada di daftar Lampiran. --}}
@foreach ($comment->attachments as $att)
    @if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $att->file_path ?? ''))
        <a href="{{ $att->file_url }}" target="_blank" rel="noopener" style="display:inline-block;margin-top:6px;"><img src="{{ $att->file_url }}" alt="{{ $att->file_name }}" style="max-width:180px;max-height:140px;border-radius:8px;object-fit:cover;display:block;"></a>
    @else
        <a href="{{ $att->file_url }}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:6px;margin-top:6px;padding:5px 9px;border-radius:8px;background:var(--surface);border:1px solid var(--border-soft);font-size:12.5px;font-weight:600;color:var(--text-heading);max-width:100%;"><i class="fa-solid fa-paperclip" style="font-size:11px;"></i><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $att->file_name ?? 'File' }}</span></a>
    @endif
@endforeach
