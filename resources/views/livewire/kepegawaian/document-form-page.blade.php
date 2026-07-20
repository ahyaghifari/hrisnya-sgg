<div>
<x-page-header
    title="{{ $record ? 'Edit Dokumen' : 'Tambah Dokumen' }}"
    subtitle="{{ $documentTypeLabel }}"
    :back="route('kepegawaian.dokumen', ['type' => $document_type])"
/>

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
        @if ($canBrowseAllCompanies)
            <div>
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="company_id" class="form-input">
                    <option value="">Pilih perusahaan...</option>
                    @foreach ($this->companyOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif  

        <div style="grid-column:1 / -1;">
            <label class="form-label">Nama Karyawan</label>
            <select wire:model="user_id" class="form-input">
                <option value="">Pilih karyawan...</option>
                @foreach ($this->userOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            @error('user_id') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">Nomor Dokumen</label>
            <input type="text" wire:model="document_number" class="form-input">
            @error('document_number') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">Tanggal Diterbitkan</label>
            <input type="date" wire:model="issued_date" class="form-input">
            @error('issued_date') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
        </div>

        @if ($document_type === \App\Enum\UserDocumentType::RETIREMENT->value)
            <div>
                <label class="form-label">Tanggal Pensiun</label>
                <input type="date" wire:model="retirement_date" class="form-input">
            </div>

            <div>
                <label class="form-label">Tipe Pensiun</label>
                <select wire:model="retirement_type" class="form-input">
                    <option value="">Pilih...</option>
                    <option value="normal">Pensiun Normal</option>
                    <option value="early">Pensiun Dini</option>
                    <option value="disability">Pensiun Cacat</option>
                </select>
            </div>

            <div style="grid-column:1 / -1;">
                <label class="form-label">Ditetapkan di</label>
                <input type="text" wire:model="set_in" class="form-input">
            </div>
        @endif

        @if (in_array($document_type, [\App\Enum\UserDocumentType::RESIGNATION->value, \App\Enum\UserDocumentType::RETIREMENT->value]))
            <div style="grid-column:1 / -1;">
                <label class="form-label">Alasan</label>
                <textarea wire:model="reason" class="form-input" rows="3"></textarea>
            </div>
        @endif

        <div style="grid-column:1 / -1;">
            <label class="form-label">Berkas Dokumen</label>

            @if ($record?->file_path && ! $file)
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:12.5px;color:var(--text-body);">
                    <i class="fa-solid fa-file-lines" style="color:var(--text-faint);"></i>
                    <a href="{{ $record->file_url }}" target="_blank" rel="noopener" style="color:var(--brand);">Lihat berkas saat ini</a>
                    <span style="color:var(--text-faint);">&middot; unggah baru untuk mengganti</span>
                </div>
            @endif

            <label class="upload-zone" style="display:block;">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px;color:var(--text-faint);margin-bottom:10px;display:block;"></i>
                <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 4px;">Klik untuk upload file</p>
                <p style="font-size:12px;color:var(--text-muted);margin:0;">PDF, JPG, PNG &middot; Maks. 10MB</p>
                <input type="file" style="display:none;" accept=".pdf,.jpg,.jpeg,.png" wire:model="file">
            </label>
            @if ($file)
                <div style="display:flex;align-items:center;gap:8px;margin-top:8px;font-size:12.5px;color:var(--text-body);">
                    <i class="fa-solid fa-file" style="color:var(--text-faint);"></i> {{ $file->getClientOriginalName() }}
                </div>
            @endif
            @error('file') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;margin-top:28px;padding-top:20px;border-top:1px solid var(--border-soft);">
        <button type="button" wire:click="save" class="btn-primary" wire:loading.attr="disabled" wire:target="save,file">
            <i class="fa-solid fa-check"></i> Simpan Dokumen
        </button>
    </div>
</div>

</div>
