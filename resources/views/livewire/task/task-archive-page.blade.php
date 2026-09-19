<div>
<x-page-header title="Arsip Board" subtitle="" />

<a href="{{ route('tugas') }}" wire:navigate class="btn-secondary btn-sm" style="margin-bottom:16px;"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Tugas</a>

@if ($this->archivedProjects->isEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:32px;text-align:center;color:var(--text-muted);font-size:13.5px;">
        <i class="fa-solid fa-box-archive" style="font-size:22px;margin-bottom:8px;display:block;"></i>
        Belum ada Board yang diarsipkan.
    </div>
@else
    <div style="display:flex;flex-direction:column;gap:10px;">
        @foreach ($this->archivedProjects as $project)
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;justify-content:space-between;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px;">
                <a href="{{ route('tugas.papan.board', $project) }}" wire:navigate style="display:flex;align-items:center;gap:12px;min-width:0;text-decoration:none;flex:1;">
                    <i class="fa-solid fa-diagram-project" style="color:{{ $project->color }};font-size:18px;"></i>
                    <span style="min-width:0;">
                        <span style="display:block;font-size:14px;font-weight:700;color:var(--text-heading);">{{ $project->name }}</span>
                        <span style="display:block;font-size:12px;color:var(--text-muted);">
                            Diarsipkan {{ $project->archived_at->translatedFormat('j F Y, H:i') }} &middot; {{ $project->tasks_count }} tugas
                        </span>
                    </span>
                </a>
                <div style="display:flex;gap:8px;">
                    <a href="{{ route('tugas.papan.board', $project) }}" wire:navigate class="btn-ghost btn-sm"><i class="fa-solid fa-eye"></i> Lihat</a>
                    @if (in_array($project->id, $this->ownedProjectIds, true))
                        <button type="button" wire:click="restore('{{ $project->id }}')" wire:confirm="Pulihkan Board ini dari arsip?" class="btn-primary btn-sm"><i class="fa-solid fa-rotate-left"></i> Pulihkan</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
</div>
