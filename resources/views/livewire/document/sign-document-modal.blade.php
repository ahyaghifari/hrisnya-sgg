<div>
@if ($this->document)
    @php $doc = $this->document; @endphp
    {{-- Panel menyatu dengan halaman (bukan overlay, jadi ikut scroll halaman); tersembunyi sampai
         event open-sign-modal, lalu halaman di-scroll ke sini. --}}
    <div x-data x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))" style="margin:20px 0 16px;">
        <div style="background:var(--surface);border:1.5px solid var(--brand);border-radius:14px;box-shadow:0 8px 28px rgba(4,44,108,0.14);overflow:hidden;">
            {{-- Header berwarna brand + ikon perisai: menandai ini tindakan resmi yang mengikat. --}}
            <div style="background:linear-gradient(135deg,var(--brand-fill) 0%,var(--brand-fill-strong) 100%);color:#fff;padding:20px 24px;display:flex;align-items:center;gap:14px;">
                <div style="width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <h3 style="font-size:17px;font-weight:700;margin:0;color:#fff;">Konfirmasi Tanda Tangan</h3>
                    <p style="font-size:12px;margin:2px 0 0;color:rgba(255,255,255,0.8);">Tindakan resmi &middot; tanda tangan Anda tercatat dan tidak dapat dibatalkan</p>
                </div>
                <button type="button" wire:click="close" style="background:none;border:none;font-size:18px;color:rgba(255,255,255,0.8);cursor:pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div style="padding:22px 24px;display:flex;flex-direction:column;gap:16px;">
                {{-- Identitas dokumen - generik dari registry `documents`, tidak menyentuh field khusus per jenis. --}}
                <div style="background:var(--surface-alt);border-radius:12px;border-left:4px solid var(--brand);padding:14px 16px;">
                    <p style="font-size:10.5px;font-weight:700;color:var(--brand);text-transform:uppercase;letter-spacing:0.06em;margin:0 0 4px;">
                        {{ \App\Enum\DocumentType::from($doc->document_type)->label() }}
                    </p>
                    <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">{{ $doc->title }}</p>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                        No. {{ $doc->documentable?->nomor ?: 'Belum bernomor' }} &middot; Terbit {{ $doc->issued_at->translatedFormat('d M Y') }}
                    </p>
                    @foreach ($doc->documentable->signSummary() as $label => $value)
                        <p style="font-size:12.5px;color:var(--text-body);margin:6px 0 0;"><span style="color:var(--text-muted);">{{ $label }}:</span> <strong>{{ $value }}</strong></p>
                    @endforeach
                    @if ($doc->documentable instanceof \App\Models\SuratTugas\SuratTugas)
                        <p style="margin:10px 0 0;"><a href="{{ route('surat-tugas.preview', $doc->documentable) }}" target="_blank" class="btn-ghost btn-xs"><i class="fa-solid fa-file-pdf"></i> Lihat PDF Surat</a></p>
                    @endif
                    @if ($this->roleLabel)
                        <p style="margin:10px 0 0;"><span style="display:inline-block;font-size:11.5px;font-weight:600;color:var(--brand);background:var(--brand-soft);border-radius:999px;padding:3px 10px;">Peran: {{ $this->roleLabel }}</span></p>
                    @endif
                </div>

                {{-- Identitas penanda tangan (Anda sendiri) - dicetak PERSIS apa yang bakal disimpan sebagai snapshot signer_name/signer_position. --}}
                <div style="display:flex;align-items:center;gap:14px;background:var(--info-soft-bg);border-radius:12px;padding:14px 16px;">
                    <x-avatar :user="auth()->user()" :size="48" />
                    <div style="min-width:0;flex:1;">
                        <p style="font-size:10.5px;font-weight:700;color:var(--info-soft-text);text-transform:uppercase;letter-spacing:0.06em;margin:0 0 2px;">Anda menandatangani sebagai</p>
                        <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">{{ auth()->user()->name }}</p>
                        <p style="font-size:12px;color:var(--text-muted);margin:0;">{{ auth()->user()->activeJobPosition?->jobPosition?->name ?? '-' }}</p>
                    </div>
                    <i class="fa-solid fa-circle-check" style="font-size:22px;color:var(--brand-success);"></i>
                </div>

                <div style="display:flex;gap:10px;align-items:flex-start;background:var(--warning-soft-bg, #fffbeb);border:1px solid var(--warning-soft-border, #fde68a);border-radius:10px;padding:10px 14px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color:var(--warning-text, #b45309);margin-top:2px;"></i>
                    <p style="font-size:12.5px;color:var(--text-body);margin:0;">Dengan memasukkan password, Anda menyatakan <strong>telah membaca dan menyetujui</strong> dokumen ini. Tanda tangan bersifat final.</p>
                </div>

                @if ($this->lockMessage)
                    <div style="display:flex;gap:10px;align-items:flex-start;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px 14px;">
                        <i class="fa-solid fa-ban" style="color:#dc2626;margin-top:2px;"></i>
                        <p style="font-size:12.5px;color:#991b1b;margin:0;">{{ $this->lockMessage }}</p>
                    </div>
                @endif

                <div>
                    <label class="form-label"><i class="fa-solid fa-lock" style="font-size:11px;margin-right:4px;"></i> Password Anda</label>
                    <input type="password" wire:model="password" class="form-input" placeholder="Masukkan password untuk melanjutkan" style="padding:12px 14px;font-size:14px;" wire:keydown.enter="confirmSign" wire:loading.attr="disabled" wire:target="confirmSign" autofocus>
                    @error('password') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
                    <button type="button" wire:click="close" wire:loading.attr="disabled" wire:target="confirmSign" class="btn-ghost" style="justify-content:center;min-width:110px;">Batal</button>
                    <button type="button" wire:click="confirmSign" wire:loading.attr="disabled" wire:target="confirmSign" class="btn-primary" style="justify-content:center;flex:1;min-width:220px;">
                        <i class="fa-solid fa-signature" wire:loading.remove wire:target="confirmSign"></i>
                        <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="confirmSign"></i>
                        <span wire:loading.remove wire:target="confirmSign">Setujui &amp; Tandatangani</span>
                        <span wire:loading wire:target="confirmSign">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
</div>
