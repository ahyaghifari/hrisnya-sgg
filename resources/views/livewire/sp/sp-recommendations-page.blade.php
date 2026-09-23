<div>
<x-page-header title="Review Rekomendasi SP" subtitle="Setujui atau tolak rekomendasi SP dari Coaching" />

<div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
    @if ($this->canBrowseAllCompanies)
        <div style="min-width:220px;">
            <label class="form-label">Perusahaan</label>
            <select wire:model.live="companyId" class="form-input">
                <option value="">Semua perusahaan</option>
                @foreach ($this->companyOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div style="min-width:220px;">
        <label class="form-label">Status</label>
        <select wire:model.live="decision" class="form-input">
            @foreach ($this->decisionOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

@php $recommendations = $this->recommendations; @endphp

<div class="pwa-list">
    @forelse ($recommendations as $rec)
        <div class="pwa-list-row" style="align-items:flex-start;flex-direction:column;gap:10px;">
            <div style="display:flex;width:100%;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">{{ $rec->user?->name ?? '-' }} &middot; {{ $rec->sp_level->getLabel() }}</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">
                        {{ $rec->reason?->name ?? '-' }} &middot; {{ $rec->source_type->getLabel() }} &middot; {{ $rec->created_at->translatedFormat('d M Y') }}
                    </span>
                    <p style="font-size:12px;color:var(--text-body);margin-top:6px;white-space:pre-line;">{{ $rec->reason_note }}</p>
                </div>
                <span class="badge" style="background:{{ $rec->decision->getColor() === 'success' ? '#dcfce7' : ($rec->decision->getColor() === 'danger' ? '#fee2e2' : '#f1f5f9') }};color:{{ $rec->decision->getColor() === 'success' ? '#15803d' : ($rec->decision->getColor() === 'danger' ? '#b91c1c' : '#64748b') }};white-space:nowrap;">
                    {{ $rec->decision->getLabel() }}
                </span>
            </div>

            @if ($rec->decision->value === 'pending')
                @if ($actioningId === $rec->id)
                    @if ($actionType === 'approve')
                        <div style="width:100%;display:flex;flex-direction:column;gap:10px;background:var(--surface-alt);border-radius:10px;padding:12px;">
                            <div>
                                <label class="form-label">Level SP</label>
                                <select wire:model="approveLevel" class="form-input">
                                    @foreach ($this->spLevelOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('approveLevel') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">Tanggal Mulai</label>
                                <input type="date" wire:model="approveStartDate" class="form-input">
                                @error('approveStartDate') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button type="button" wire:click="confirmAction" class="btn-primary">Konfirmasi Setujui</button>
                                <button type="button" wire:click="cancelAction" class="btn-ghost">Batal</button>
                            </div>
                        </div>
                    @else
                        <div style="width:100%;display:flex;flex-direction:column;gap:10px;background:var(--surface-alt);border-radius:10px;padding:12px;">
                            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">SP tidak akan diterbitkan. Aksi ini tidak bisa dibatalkan.</p>
                            <div style="display:flex;gap:8px;">
                                <button type="button" wire:click="confirmAction" class="btn-primary" style="background:var(--danger-text);border-color:var(--danger-text);">Konfirmasi Tolak</button>
                                <button type="button" wire:click="cancelAction" class="btn-ghost">Batal</button>
                            </div>
                        </div>
                    @endif
                @else
                    <div style="display:flex;gap:8px;">
                        <button type="button" wire:click="chooseAction('{{ $rec->id }}', 'approve')" class="btn-primary" style="font-size:12.5px;">
                            <i class="fa-solid fa-check"></i> Setujui
                        </button>
                        <button type="button" wire:click="chooseAction('{{ $rec->id }}', 'reject')" class="btn-ghost" style="font-size:12.5px;color:var(--danger-text);border-color:var(--danger-soft-border);">
                            <i class="fa-solid fa-xmark"></i> Tolak
                        </button>
                    </div>
                @endif
            @endif
        </div>
    @empty
        <div class="pwa-list-row" style="opacity:0.7;">
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">Tidak ada rekomendasi</p>
                <span style="font-size:11.5px;color:var(--text-muted);">Rekomendasi SP dari Coaching akan muncul di sini.</span>
            </div>
        </div>
    @endforelse
</div>

<div style="margin-top:16px;">
    {{ $recommendations->links('livewire.partials.pagination') }}
</div>
</div>
