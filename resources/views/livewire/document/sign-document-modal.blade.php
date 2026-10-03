<div>
@if ($this->document)
    @php $doc = $this->document; @endphp
    <div class="modal-overlay" wire:click="close">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Konfirmasi Tanda Tangan</h3>
                <button type="button" wire:click="close" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
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
                    @if ($this->roleLabel)
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Peran yang Anda tandatangani: <strong>{{ $this->roleLabel }}</strong></p>
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
                <button type="button" wire:click="close" class="btn-ghost" style="flex:1;justify-content:center;">Batal</button>
            </div>
        </div>
    </div>
@endif
</div>
