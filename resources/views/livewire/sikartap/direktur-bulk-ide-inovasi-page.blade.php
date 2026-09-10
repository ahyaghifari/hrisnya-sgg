<div>
    <div class="page-hero violet" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('sikartap.dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Dashboard Sikartap</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Pengesahan Ide Inovasi</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">💡 Pengesahan Ide Inovasi - Massal</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $this->pending->count() }} ide inovasi menunggu pengesahan Anda</p>
        </div>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border-soft);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;color:var(--text-body);cursor:pointer;">
                <span class="custom-checkbox" style="--accent:#7c3aed;">
                    <input type="checkbox" wire:click="toggleSelectAll" @checked($this->selectedCount === $this->pending->count() && $this->selectedCount > 0)>
                    <span class="box"><i class="fa-solid fa-check"></i></span>
                </span>
                Pilih Semua ({{ $this->pending->count() }})
            </label>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" wire:click="openConfirm('approve')" @disabled($this->selectedCount === 0) class="btn-primary" style="padding:8px 16px;font-size:12.5px;opacity:{{ $this->selectedCount === 0 ? '0.5' : '1' }};">
                    ✓ Sahkan Terpilih ({{ $this->selectedCount }})
                </button>
                <button type="button" wire:click="openConfirm('reject')" @disabled($this->selectedCount === 0) class="btn-ghost" style="padding:8px 16px;font-size:12.5px;color:var(--danger-text);border-color:var(--danger-soft-border);opacity:{{ $this->selectedCount === 0 ? '0.5' : '1' }};">
                    ✗ Tolak Terpilih ({{ $this->selectedCount }})
                </button>
            </div>
        </div>

        <div class="pwa-list">
            @foreach ($this->pending as $sikartap)
                <div class="pwa-list-row" style="align-items:center;">
                    <label class="custom-checkbox" style="--accent:#7c3aed;flex-shrink:0;">
                        <input type="checkbox" wire:model.live="selected" value="{{ $sikartap->id }}">
                        <span class="box"><i class="fa-solid fa-check"></i></span>
                    </label>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $sikartap->user->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            {{ $sikartap->user->nip }} &middot; {{ $sikartap->user->company?->name }}
                            @if ($sikartap->ideInovasi?->nilai_akhir_atasan !== null)
                                &middot; Nilai atasan: {{ $sikartap->ideInovasi->nilai_akhir_atasan }}
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('sikartap.pengajuan.show', $sikartap) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;flex-shrink:0;">
                        👁️ Lihat Detail
                    </a>
                </div>
            @endforeach
        </div>
    </div>

    @if ($showConfirm)
        <div class="modal-overlay" wire:click="closeConfirm">
            <div class="modal-box" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">
                        {{ $confirmAction === 'approve' ? 'Sahkan' : 'Tolak' }} {{ $this->selectedCount }} Ide Inovasi
                    </h3>
                    <button type="button" wire:click="closeConfirm" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px;">
                    Ide inovasi yang Anda centang akan {{ $confirmAction === 'approve' ? 'disahkan' : 'ditolak' }} sekaligus. Yang ditolak terblokir sampai disahkan ulang.
                </p>
                <div style="margin-bottom:16px;">
                    <label class="form-label">Catatan {{ $confirmAction === 'reject' ? '(wajib)' : '(opsional)' }}</label>
                    <textarea wire:model="note" class="form-input" rows="3" placeholder="{{ $confirmAction === 'approve' ? 'Mis. Disahkan sesuai penilaian atasan' : 'Alasan penolakan...' }}"></textarea>
                    @error('note') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" wire:click="confirmDecision" class="{{ $confirmAction === 'approve' ? 'btn-primary' : 'btn-ghost' }}" style="flex:1;justify-content:center;{{ $confirmAction === 'reject' ? 'color:var(--danger-text);border-color:var(--danger-soft-border);' : '' }}">
                        Ya, {{ $confirmAction === 'approve' ? 'Sahkan' : 'Tolak' }} Semua
                    </button>
                    <button type="button" wire:click="closeConfirm" class="btn-ghost" style="flex:1;justify-content:center;">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
