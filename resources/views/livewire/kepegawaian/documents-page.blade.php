<div>
<x-page-header title="Dokumen Kepegawaian" subtitle="Kelola surat & dokumen resmi karyawan" />

<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <a href="{{ route('kepegawaian.dokumen.create', ['type' => $documentType]) }}" wire:navigate class="btn-primary">
        <i class="fa-solid fa-upload"></i> Tambah Dokumen
    </a>
</div>

<div class="doc-type-layout">
    {{-- Kiri: pilih jenis dokumen — server-side (bukan Alpine x-show) karena tiap jenis punya
         pagination sendiri, beda dari tab Dokumen di halaman detail karyawan. --}}
    <div class="doc-type-nav">
        @foreach ($documentTypes as $type)
            <button type="button" wire:click="setType('{{ $type->value }}')" class="doc-type-btn {{ $documentType === $type->value ? 'is-active' : '' }}">
                <i class="fa-solid fa-file-lines"></i>
                <span>{{ $type->label() }}</span>
                <span class="doc-type-count">{{ $this->documentCounts->get($type->value, 0) }}</span>
            </button>
        @endforeach
    </div>

    {{-- Kanan: filter + list dokumen jenis terpilih --}}
    <div style="flex:1;min-width:0;">
        {{-- Filter status: default Aktif (exclude soft-deleted) — Tidak Aktif khusus nampilin
             dokumen yang sudah dihapus, buat dipulihkan kalau perlu. Sengaja kecil/plain
             (bukan .section-nav-btn), ini cuma filter sekunder, bukan navigasi utama. --}}
        <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;margin-bottom:14px;">
            <button type="button" wire:click="setStatus('aktif')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === 'aktif' ? 'var(--surface)' : 'transparent' }};color:{{ $status === 'aktif' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === 'aktif' ? 'var(--shadow-card)' : 'none' }};">Aktif</button>
            <button type="button" wire:click="setStatus('tidak_aktif')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === 'tidak_aktif' ? 'var(--surface)' : 'transparent' }};color:{{ $status === 'tidak_aktif' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === 'tidak_aktif' ? 'var(--shadow-card)' : 'none' }};">Tidak Aktif</button>
        </div>

        <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
            @if ($canBrowseAllCompanies)
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

            <div style="min-width:220px;flex:1;">
                <label class="form-label">Cari</label>
                <div style="position:relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                    <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama karyawan, nomor dokumen..." style="padding-left:38px;">
                </div>
            </div>
        </div>

        @php $documents = $this->documents; @endphp

        <!-- MOBILE: card manual -->
        <div class="md:hidden">
            @if ($documents->isEmpty())
                <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada dokumen {{ mb_strtolower(\App\Enum\UserDocumentType::from($documentType)->label()) }}.</p>
                </div>
            @else
                <div class="pwa-list">
                    @foreach ($documents as $document)
                        <div class="pwa-list-row">
                            <div class="pwa-tile-icon neutral"><i class="fa-solid fa-file-lines"></i></div>
                            <div style="flex:1;min-width:0;">
                                <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $document->user_name }}</p>
                                <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                    {{ $document->document_number ?: 'Tanpa nomor' }} &middot; {{ $document->issued_date?->translatedFormat('d M Y') ?? '-' }}
                                </p>
                                @if ($canBrowseAllCompanies && $document->user?->company?->name)
                                    <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">{{ $document->user->company->name }}</p>
                                @endif
                            </div>
                            <div style="display:flex;gap:4px;flex-shrink:0;">
                                @if ($status === 'tidak_aktif')
                                    <button type="button" wire:click="restore('{{ $document->id }}')" wire:confirm="Pulihkan dokumen ini?" class="btn-ghost" style="padding:5px 9px;font-size:11px;color:var(--brand-success);" title="Pulihkan"><i class="fa-solid fa-rotate-left"></i></button>
                                @else
                                    <a href="{{ $document->file_url }}" target="_blank" rel="noopener" class="btn-ghost" style="padding:5px 9px;font-size:11px;" title="Lihat"><i class="fa-solid fa-download"></i></a>
                                    <a href="{{ route('kepegawaian.dokumen.edit', ['record' => $document]) }}" wire:navigate class="btn-ghost" style="padding:5px 9px;font-size:11px;" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <button type="button" wire:click="delete('{{ $document->id }}')" wire:confirm="Yakin hapus dokumen ini?" class="btn-ghost" style="padding:5px 9px;font-size:11px;color:var(--danger-text);" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top:16px;">
                    {{ $documents->links('livewire.partials.pagination') }}
                </div>
            @endif
        </div>

        <!-- DESKTOP: tabel -->
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $documents->isEmpty() ? 'display:none;' : '' }}">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Karyawan</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nomor Dokumen</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tanggal Diterbitkan</th>
                            @if ($canBrowseAllCompanies)
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                            @endif
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr wire:key="'doc-'.{{ $document->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $document->user_name }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $document->document_number ?: '-' }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $document->issued_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                @if ($canBrowseAllCompanies)
                                    <td style="padding:9px 14px;white-space:nowrap;">{{ $document->user?->company?->name ?? '-' }}</td>
                                @endif
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    @if ($status === 'tidak_aktif')
                                        <button type="button" wire:click="restore('{{ $document->id }}')" wire:confirm="Pulihkan dokumen ini?" class="btn-ghost" style="padding:5px 10px;font-size:11px;color:var(--brand-success);" title="Pulihkan"><i class="fa-solid fa-rotate-left"></i></button>
                                    @else
                                        <a href="{{ $document->file_url }}" target="_blank" rel="noopener" class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat"><i class="fa-solid fa-download"></i></a>
                                        <a href="{{ route('kepegawaian.dokumen.edit', ['record' => $document]) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                        <button type="button" wire:click="delete('{{ $document->id }}')" wire:confirm="Yakin hapus dokumen ini?" class="btn-ghost" style="padding:5px 10px;font-size:11px;color:var(--danger-text);" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($documents->isEmpty())
                <div style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada dokumen jenis ini.</p>
                </div>
            @endif

            <div style="margin-top:16px;">
                {{ $documents->links('livewire.partials.pagination') }}
            </div>
        </div>
    </div>
</div>

</div>
