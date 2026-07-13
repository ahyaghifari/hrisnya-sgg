<div>
@php
    // ponytail: warna "acak" tapi stabil per board (hash id ke palet tetap), bukan random()
    // asli — supaya warnanya tidak berubah-ubah tiap kali halaman di-render ulang.
    $palette = ['#042c6c', '#047C54', '#92400e', '#5b21b6', '#0f766e', '#be123c', '#3730a3', '#b45309'];
    $stripeColor = fn (string $id) => $palette[crc32($id) % count($palette)];
@endphp

<x-page-header title="Kanban" />

<div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <a href="{{ route('kanban.personal') }}" wire:navigate class="btn-primary">
        <i class="fa-solid fa-user" style="font-size:12px;"></i> BoardKu
    </a>
    @if ($this->canCreateBoard)
        <a href="{{ route('kanban.create') }}" wire:navigate class="btn-success">
            <i class="fa-solid fa-plus" style="font-size:12px;"></i> Board Baru
        </a>
    @endif
</div>

@if ($this->boards->isEmpty())
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada board. {{ $this->canCreateBoard ? 'Buat board baru untuk mulai kelola project.' : 'Minta owner board untuk menambahkanmu sebagai member.' }}</p>
    </div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
        @foreach ($this->boards as $board)
            <a href="{{ route('kanban.show', ['board' => $board]) }}" wire:navigate class="pwa-list" style="display:block;padding:16px;text-decoration:none;border-left:5px solid {{ $stripeColor($board->id) }};">
                <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">{{ $board->name }}</p>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">{{ $board->description ?: 'Tanpa deskripsi' }}</p>
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--text-muted);">
                    <span><i class="fa-solid fa-list-check" style="font-size:11px;"></i> {{ $board->tasks_count }} task</span>
                    <span>Owner: {{ $board->owner?->name }}</span>
                </div>
            </a>
        @endforeach
    </div>
@endif
</div>
