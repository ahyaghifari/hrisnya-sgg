<div>
<x-page-header title="Detail Pelatihan Eksternal" :back="route('pelatihan.eksternal')" />

<div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;margin-bottom:16px;">
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Pemohon</h4>
        <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0;">{{ $record->user->name }}</p>
        <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">{{ $record->user->nip }}</p>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Detail Pelatihan</h4>
        <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0;">{{ $record->title }}</p>
        <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">{{ $record->organizer ?? '-' }}</p>
        <span class="badge" style="margin-top:8px;">{{ ucfirst($record->status) }}</span>
    </div>
</div>

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">Penilaian Ikatan Dinas</h3>
        @if ($record->ikatan_dinas_tahun)
            <span class="badge" style="background:rgba(4,124,84,0.12);color:var(--brand-success);">
                Tersimpan: {{ rtrim(rtrim(number_format($record->ikatan_dinas_tahun, 2, ',', '.'), '0'), ',') }} Tahun
                @if ($record->ikatan_dinas_selesai)
                    &middot; Selesai {{ $record->ikatan_dinas_selesai->translatedFormat('d F Y') }}
                @endif
            </span>
        @endif
    </div>

    <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 12px;">
        Lama ikatan dinas dihitung dari jumlah skor kategori yang dipilih di tiap variabel (konteks Pelatihan Eksternal) — mesin skor yang sama dengan honorarium trainer Dokter Spesialis, cuma di sini hasilnya ditafsir sebagai tahun ikatan dinas, bukan nominal Rupiah.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2" style="gap:20px 28px;">
        @forelse ($this->variables as $variable)
            <div wire:key="'honor-var-'.{{ $variable->id }}">
                <label class="form-label">{{ $variable->name }}</label>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
                    @foreach ($variable->categories as $category)
                        <label style="display:flex;align-items:center;gap:9px;font-size:12.5px;color:var(--text-body);cursor:pointer;">
                            <span class="custom-radio">
                                <input type="radio" name="honor-var-{{ $variable->id }}"
                                    wire:model="categorySelections.{{ $variable->id }}"
                                    value="{{ $category->id }}">
                                <span class="dot"></span>
                            </span>
                            {{ $category->name }}
                            <span style="color:var(--text-faint);">(skor {{ $category->score }})</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="md:col-span-2" style="font-size:12.5px;color:var(--text-muted);margin:0;">
                Belum ada variabel skor untuk konteks "Eksternal" — atur dulu di halaman Master Honorarium.
            </p>
        @endforelse
    </div>
    @error('categorySelections') <p style="color:var(--danger-text);font-size:12px;margin-top:8px;">{{ $message }}</p> @enderror

    @if ($this->variables->isNotEmpty())
        <div style="display:flex;justify-content:flex-end;margin-top:16px;">
            <button type="button" wire:click="saveHonor" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                <i class="fa-solid fa-save"></i> Simpan Penilaian
            </button>
        </div>
    @endif
</div>
</div>
