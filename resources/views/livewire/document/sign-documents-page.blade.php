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

<livewire:document.sign-document-modal :document-id="$initialSigningId" />

</div>
