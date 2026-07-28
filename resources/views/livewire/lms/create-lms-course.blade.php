<div>
    <style>
        .date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    </style>

    <x-page-header title="Tambah Pelatihan" :back="route('pelatihan.kelola')" />

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
            <div>
                <label class="form-label">Kategori <span style="color:var(--danger-text);">*</span></label>
                <select wire:model="category_id" class="form-input">
                    <option value="">Pilih kategori...</option>
                    @foreach ($this->categoryOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Kode Pelatihan <span style="color:var(--danger-text);">*</span></label>
                <input type="text" wire:model="code" class="form-input" placeholder="mis. TRN-001">
                @error('code') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div style="grid-column:1 / -1;">
                <label class="form-label">Judul Pelatihan <span style="color:var(--danger-text);">*</span></label>
                <input type="text" wire:model="title" class="form-input">
                @error('title') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Tipe Peserta</label>
                <select wire:model="type_nakes" class="form-input">
                    <option value="all">Nakes & Non-Nakes</option>
                    <option value="nakes">Nakes</option>
                    <option value="non-nakes">Non-Nakes</option>
                </select>
            </div>

            <div>
                <label class="form-label">Skor Minimum Kelulusan</label>
                <input type="number" wire:model="minimum_score" class="form-input" min="0">
                @error('minimum_score') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Batas Waktu Penyelesaian (opsional)</label>
                <input type="date" wire:model="due_date" class="form-input">
                @error('due_date') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div style="grid-column:1 / -1;">
                <label class="form-label">Kompetensi Terkait <span style="color:var(--danger-text);">*</span></label>
                <div wire:ignore>
                    <select data-tom-select data-placeholder="Pilih kompetensi..." multiple wire:model="competencyIds">
                        @foreach ($this->competencyOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @error('competencyIds') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div style="grid-column:1 / -1;">
                <label class="form-label">Thumbnail Pelatihan (opsional)</label>
                <label class="upload-zone" style="display:block;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px;color:var(--text-faint);margin-bottom:10px;display:block;"></i>
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 4px;">Klik untuk upload gambar</p>
                    <p style="font-size:12px;color:var(--text-muted);margin:0;">JPG, PNG &middot; Maks. 10MB</p>
                    <input type="file" style="display:none;" accept="image/*" wire:model="thumbnail">
                </label>
                @if ($thumbnail)
                    <div style="display:flex;align-items:center;gap:8px;margin-top:8px;font-size:12.5px;color:var(--text-body);">
                        <i class="fa-solid fa-image" style="color:var(--text-faint);"></i> {{ $thumbnail->getClientOriginalName() }}
                    </div>
                @endif
                @error('thumbnail') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>
        </div>

        <div style="border-top:1px solid var(--border-soft);margin:24px 0 20px;"></div>

        <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 16px;">Jadwal Pelatihan</h3>

        <div class="toggle-wrapper" wire:click="toggleOneDay">
            <div class="toggle {{ ! $is_one_day ? 'on' : '' }}"></div>
            <div>
                <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 2px;">Aktifkan jika pelatihan lebih dari 1 hari</p>
                <p style="font-size:12px;color:var(--text-muted);margin:0;">Pilih tanggal mulai dan tanggal selesai</p>
            </div>
        </div>

        <div style="margin-top:16px;">
            @if ($is_one_day)
                <div>
                    <label class="form-label">Tanggal Pelatihan <span style="color:var(--danger-text);">*</span></label>
                    <input type="date" class="form-input" wire:model="start_date">
                    @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label class="form-label">Tanggal Pelatihan <span style="color:var(--danger-text);">*</span></label>
                    <div class="date-grid">
                        <div>
                            <input type="date" class="form-input" wire:model="start_date">
                            <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">Tanggal Mulai</p>
                            @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <input type="date" class="form-input" wire:model="end_date">
                            <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">Tanggal Selesai</p>
                            @error('end_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div style="border-top:1px solid var(--border-soft);margin:24px 0 20px;"></div>

        <div class="toggle-wrapper" wire:click="toggleRequiresFeedback">
            <div class="toggle {{ $requires_feedback ? 'on' : '' }}"></div>
            <div>
                <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 2px;">Aktifkan umpan balik publik</p>
                <p style="font-size:12px;color:var(--text-muted);margin:0;">QR umpan balik dibuat otomatis begitu pelatihan di-publish, bisa dibagikan ke peserta lewat link/QR tanpa perlu login.</p>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:28px;padding-top:20px;border-top:1px solid var(--border-soft);">
            <button type="button" wire:click="save" class="btn-primary" wire:loading.attr="disabled" wire:target="save,thumbnail">
                <i class="fa-solid fa-check"></i> Simpan
            </button>
        </div>
    </div>
</div>
