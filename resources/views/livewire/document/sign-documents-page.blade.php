<div>
<x-page-header title="Tanda Tangan Dokumen" :back="route('dashboard')" />

<p style="font-size:12.5px;color:var(--text-muted);margin:0 0 16px;">
    Dokumen di bawah menugaskan Anda sebagai salah satu penanda tangan dan menunggu konfirmasi Anda sendiri.
</p>

@php $pending = $this->pendingDocuments; @endphp

@if ($pending->isEmpty())
    <div class="card-surface" style="text-align:center;padding:32px 16px;">
        <i class="fa-solid fa-circle-check" style="font-size:22px;color:#15803d;margin-bottom:8px;display:block;"></i>
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Tidak ada dokumen yang menunggu tanda tangan Anda.</p>
    </div>
@else
    <div class="pwa-list">
        @foreach ($pending as $doc)
            @php $roleLabel = $doc->documentable->expectedSignerRoles()[$doc->pendingRoleFor(auth()->user())] ?? null; @endphp
            <div class="pwa-list-row">
                <div class="pwa-tile-icon warn"><i class="fa-solid fa-signature"></i></div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $doc->title }}</p>
                    <p style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                        {{ \App\Enum\DocumentType::from($doc->document_type)->label() }} &middot; No. {{ $doc->document_number ?: '-' }}
                        @if ($roleLabel) &middot; peran Anda: {{ $roleLabel }} @endif
                    </p>
                </div>
                <button type="button" wire:click="openSignModal('{{ $doc->id }}')" class="btn-primary" style="padding:7px 14px;font-size:12px;">
                    <i class="fa-solid fa-signature"></i> Tandatangani
                </button>
            </div>
        @endforeach
    </div>
@endif

{{-- Modal konfirmasi - identitas dokumen + identitas penanda tangan (Anda sendiri), baru password. --}}
@if ($this->signingDocument)
    @php $doc = $this->signingDocument; @endphp
    <div class="modal-overlay" wire:click="closeSignModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Konfirmasi Tanda Tangan</h3>
                <button type="button" wire:click="closeSignModal" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div style="display:flex;flex-direction:column;gap:14px;">
                {{-- Identitas dokumen - generik dari registry `documents`, tidak menyentuh field khusus per jenis. --}}
                <div style="background:var(--surface-alt);border-radius:10px;padding:12px 14px;">
                    <p style="font-size:10.5px;color:var(--text-faint);text-transform:uppercase;letter-spacing:0.03em;margin:0 0 4px;">
                        {{ \App\Enum\DocumentType::from($doc->document_type)->label() }}
                    </p>
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">{{ $doc->title }}</p>
                    <p style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                        No. {{ $doc->document_number ?: '-' }} &middot; Terbit {{ $doc->issued_at->translatedFormat('d M Y') }}
                    </p>
                    @if ($this->signingRoleLabel)
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Peran yang Anda tandatangani: <strong>{{ $this->signingRoleLabel }}</strong></p>
                    @endif
                </div>

                {{-- Identitas penanda tangan (Anda sendiri) - dicetak PERSIS apa yang bakal disimpan sebagai snapshot signer_name/signer_position. --}}
                <div style="display:flex;align-items:center;gap:12px;background:var(--info-soft-bg);border-radius:10px;padding:12px 14px;">
                    <x-avatar :user="auth()->user()" :size="42" />
                    <div style="min-width:0;">
                        <p style="font-size:10.5px;color:var(--info-soft-text);text-transform:uppercase;letter-spacing:0.03em;margin:0 0 2px;">Anda menandatangani sebagai</p>
                        <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">{{ auth()->user()->name }}</p>
                        <p style="font-size:11.5px;color:var(--text-muted);margin:0;">{{ auth()->user()->activeJobPosition?->jobPosition?->name ?? '-' }}</p>
                    </div>
                </div>

                <p style="font-size:12px;color:var(--text-body);margin:0;">Masukkan password Anda untuk menyatakan bahwa Anda telah membaca dan menyetujui dokumen ini.</p>

                <div>
                    <label class="form-label">Password Anda</label>
                    <input type="password" wire:model="password" class="form-input" wire:keydown.enter="confirmSign" autofocus>
                    @error('password') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:16px;">
                <button type="button" wire:click="confirmSign" wire:loading.attr="disabled" class="btn-primary" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-check"></i> Setujui &amp; Tandatangani
                </button>
                <button type="button" wire:click="closeSignModal" class="btn-ghost" style="flex:1;justify-content:center;">Batal</button>
            </div>
        </div>
    </div>
@endif

</div>
