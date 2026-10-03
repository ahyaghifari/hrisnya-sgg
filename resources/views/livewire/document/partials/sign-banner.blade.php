{{-- Banner "menunggu tanda tangan Anda" + modal tanda tangan, untuk halaman detail dokumen mana pun.
     Butuh $record (model Signable ber-relasi `document`) dan listener #[On('document-signed')] di komponen induk. --}}
@php
    $signDoc = $record->document;
    $signDoc?->loadMissing(['documentable', 'signatures']);
    $signRole = $signDoc?->pendingRoleFor(auth()->user());
@endphp
@if ($signRole)
    <div class="card-surface" style="margin-bottom:16px;border:1.5px solid #0ea5e9;background:#f0f9ff;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <p style="font-size:13px;font-weight:700;color:#0369a1;margin:0;"><i class="fa-solid fa-signature"></i> Menunggu Tanda Tangan Anda</p>
                <p style="font-size:12.5px;color:var(--text-body);margin:4px 0 0;">
                    Anda ditugaskan sebagai <strong>{{ $signDoc->documentable->expectedSignerRoles()[$signRole] ?? $signRole }}</strong>. Periksa isi dokumen, lalu tanda tangani.
                </p>
            </div>
            <button type="button" wire:click="$dispatch('open-sign-modal', { id: '{{ $signDoc->id }}' })" class="btn-primary" style="white-space:nowrap;">
                <i class="fa-solid fa-signature"></i> Tanda Tangani
            </button>
        </div>
    </div>
@endif

<livewire:document.sign-document-modal />
