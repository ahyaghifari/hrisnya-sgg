<div>
    <x-page-header title="Counseling" size="lg" />

    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <button type="button" wire:click="openConsultModal" class="btn-primary">
            <i class="fa-solid fa-comment-medical"></i> Konsul
        </button>
    </div>

    <div class="pwa-list">
        @forelse ($counselings as $counseling)
            <div class="pwa-list-row">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">{{ \Illuminate\Support\Str::limit($counseling->issue, 60) }}</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">
                        {{ $counseling->created_at->translatedFormat('d M Y') }}
                        @if ($counseling->response)
                            &middot; Ditanggapi {{ $counseling->respondedBy?->name ?? 'HR' }}
                        @endif
                    </span>
                </div>
                <span class="badge {{ $counseling->status === 'done' ? 'badge-approved' : 'badge-pending' }}">
                    {{ \App\Enum\CounselingStatus::tryFrom($counseling->status)?->getLabel() ?? $counseling->status }}
                </span>
            </div>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada counseling</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Keluhan/counseling yang Anda ajukan akan muncul di sini.</span>
                </div>
            </div>
        @endforelse
    </div>

    @if ($showConsultModal)
        <div class="modal-overlay" wire:click="closeConsultModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Konsul</h3>
                <form wire:submit="submitConsult" style="display:flex;flex-direction:column;gap:14px;">
                    <div>
                        <label class="form-label">Keluhan / Yang ingin dikonsultasikan *</label>
                        <textarea class="form-input" rows="5" wire:model="issue" placeholder="Ceritakan keluhan atau hal yang ingin Anda konsultasikan..."></textarea>
                        @error('issue') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;">
                        <button type="button" wire:click="closeConsultModal" class="btn-ghost">Batal</button>
                        <button type="submit" class="btn-primary">Kirim</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
