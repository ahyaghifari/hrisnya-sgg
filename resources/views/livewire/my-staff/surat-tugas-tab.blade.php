<div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:12px;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Surat perintah tugas kerja di luar kantor untuk bawahan Anda.</p>
        @unless ($showForm)
            <button type="button" wire:click="buat" class="btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Buat Surat Tugas
            </button>
        @endunless
    </div>

    @if ($showForm)
        <form wire:submit="ajukan" class="form-card" style="margin-bottom:18px;">
            <div style="margin-bottom:14px;max-width:320px;">
                <label class="form-label">Nomor Surat</label>
                <input type="text" wire:model="nomor" class="form-input">
            </div>

            <div style="margin-bottom:14px;">
                <label class="form-label">Ditugaskan <span style="color:#dc2626;">*</span></label>
                <select multiple data-tom-select data-avatar data-placeholder="Cari nama atau NIP karyawan..." wire:model="peserta" class="form-input">
                    @foreach ($this->bawahan as $b)
                        @php
                            $photoPath = $b->profilePhotoPath();
                            $avatar = [
                                'photo' => $photoPath ? \Illuminate\Support\Facades\Storage::url($photoPath) : null,
                                'initials' => collect(preg_split('/\s+/', trim($b->name)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''),
                                'sub' => $b->nip,
                            ];
                        @endphp
                        <option value="{{ $b->id }}" data-data="{{ json_encode($avatar) }}">{{ $b->name }}</option>
                    @endforeach
                </select>
                @error('peserta') <p style="color:#dc2626;font-size:12px;margin:4px 0 0;">{{ $message }}</p> @enderror
            </div>

            <div style="margin-bottom:14px;">
                <label class="form-label">Tugas / Keperluan <span style="color:#dc2626;">*</span></label>
                <textarea wire:model="tugas" rows="3" class="form-input"></textarea>
                @error('tugas') <p style="color:#dc2626;font-size:12px;margin:4px 0 0;">{{ $message }}</p> @enderror
            </div>

            <div style="margin-bottom:14px;">
                <label class="form-label">Tanggal <span style="color:#dc2626;">*</span></label>
                @foreach ($periode as $i => $row)
                    <div wire:key="periode-{{ $i }}" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap;">
                        <input type="date" wire:model="periode.{{ $i }}.start_date" class="form-input" style="max-width:180px;">
                        <span style="color:var(--text-muted);">s/d</span>
                        <input type="date" wire:model="periode.{{ $i }}.end_date" class="form-input" style="max-width:180px;" title="Kosongkan = sampai selesai">
                        @if (count($periode) > 1)
                            <button type="button" wire:click="removePeriode({{ $i }})" class="btn-ghost btn-xs"><i class="fa-solid fa-xmark"></i></button>
                        @endif
                    </div>
                    @error("periode.$i.start_date") <p style="color:#dc2626;font-size:12px;margin:0;">{{ $message }}</p> @enderror
                    @error("periode.$i.end_date") <p style="color:#dc2626;font-size:12px;margin:0;">{{ $message }}</p> @enderror
                @endforeach
                <button type="button" wire:click="addPeriode" class="btn-ghost btn-xs"><i class="fa-solid fa-plus"></i> Tambah rentang tanggal</button>
                <p style="font-size:11px;color:var(--text-muted);margin:4px 0 0;">Tanggal selesai kosong = sampai selesai (presensi hanya dicatat untuk tanggal mulai). Tanggal terputus: tambah rentang baru.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap:14px;margin-bottom:14px;">
                <div>
                    <label class="form-label">Waktu</label>
                    <input type="text" wire:model="waktu" class="form-input" placeholder="mis. 08.00 - 16.00 atau Sampai selesai">
                </div>
                <div>
                    <label class="form-label">Tempat <span style="color:#dc2626;">*</span></label>
                    <input type="text" wire:model="tempat" class="form-input">
                    @error('tempat') <p style="color:#dc2626;font-size:12px;margin:4px 0 0;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Transportasi</label>
                    <input type="text" wire:model="transportasi" class="form-input" placeholder="mis. Kendaraan pribadi">
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label class="form-label">Lain-lain (opsional)</label>
                <textarea wire:model="lain_lain" rows="2" class="form-input" placeholder="Muncul di baris &quot;Dan lain-lain&quot; pada Surat Perintah Perjalanan Dinas"></textarea>
            </div>

            <div style="margin-bottom:14px;">
                <label class="form-label">Pemberi Tugas</label>
                @if ($this->penandatangan)
                    <p style="font-size:13px;margin:0;"><strong>{{ $this->penandatangan->nama }}</strong> &middot; {{ $this->penandatangan->jabatan }}</p>
                @else
                    <p style="font-size:13px;color:#dc2626;margin:0;">Penanda tangan belum diatur. Minta HC mengaturnya di Pengaturan Kepegawaian.</p>
                @endif
                @error('penandatangan') <p style="color:#dc2626;font-size:12px;margin:4px 0 0;">{{ $message }}</p> @enderror
            </div>

            <div style="display:flex;gap:8px;">
                <button type="button" wire:click="simpanDraft" wire:loading.attr="disabled" class="btn-secondary"><i class="fa-solid fa-floppy-disk"></i> Simpan Draft</button>
                <button type="submit" wire:loading.attr="disabled" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Ajukan untuk Ditandatangani</button>
                <button type="button" wire:click="batal" class="btn-ghost">Batal</button>
            </div>
        </form>
    @endif

    <div class="pwa-list">
        @forelse ($this->daftar as $surat)
            <div class="pwa-list-row" wire:key="st-{{ $surat->id }}">
                <div class="pwa-tile-icon warn"><i class="fa-solid fa-file-signature"></i></div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">
                        {{ $surat->nomor ?: 'Tanpa nomor' }} &middot; {{ $surat->tempat }}
                    </p>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                        {{ $surat->peserta->map(fn ($p) => $p->user?->name)->filter()->implode(', ') }}
                        &middot; {{ $surat->periodeLabel() }}
                    </p>
                </div>
                <a href="{{ route('surat-tugas.preview', $surat) }}" target="_blank" class="btn-ghost btn-xs" title="Lihat PDF (Surat Tugas + Perjalanan Dinas)"><i class="fa-solid fa-file-pdf"></i></a>
                @if ($surat->isDraft())
                    <span class="badge badge-cancelled">Draft</span>
                    <button type="button" wire:click="edit('{{ $surat->id }}')" class="btn-ghost btn-xs" title="Ubah"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" wire:click="kirim('{{ $surat->id }}')" wire:confirm="Ajukan surat ini untuk ditandatangani? Isi tidak bisa diubah lagi." class="btn-primary btn-xs" title="Ajukan"><i class="fa-solid fa-paper-plane"></i></button>
                    <button type="button" wire:click="hapus('{{ $surat->id }}')" wire:confirm="Hapus draft ini?" class="btn-ghost btn-xs" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                @else
                    <span class="badge {{ $surat->isTerbit() ? 'badge-approved' : 'badge-pending' }}">
                        {{ $surat->isTerbit() ? 'Terbit' : 'Menunggu TTD' }}
                    </span>
                    <button type="button" wire:click="toggleAudit('{{ $surat->id }}')" class="btn-ghost btn-xs" title="Riwayat audit"><i class="fa-solid fa-clipboard-list"></i></button>
                @endif
            </div>
            @if ($auditFor === $surat->id && $surat->document)
                <div wire:key="audit-{{ $surat->id }}" style="padding:0 12px;">
                    <livewire:document.audit-trail :document-id="$surat->document->id" :key="'audit-trail-'.$surat->id" />
                </div>
            @endif
        @empty
            <div class="ms-empty">
                <i class="fa-solid fa-file-signature"></i>
                <span>Belum ada surat perintah tugas.</span>
            </div>
        @endforelse
    </div>
</div>
