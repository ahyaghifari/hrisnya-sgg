<div class="form-page-wrap">
    <style>
        /* 2 kolom tetap (juga di HP), ikon di atas label. */
        .ot-type-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .ot-type-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 16px 12px; border-radius: 12px; border: 1.5px solid var(--border); background: var(--surface); color: var(--text-heading); font-family: inherit; font-size: 13.5px; font-weight: 700; text-align: center; cursor: pointer; transition: background 0.12s ease, border-color 0.12s ease; }
        .ot-type-btn i { font-size: 22px; }
        .ot-type-btn:hover { background: var(--surface-alt); }
        /* Warna aktif = warna Lembur (hijau, sama dengan hero halaman & tombol Lembur di menu). */
        .ot-type-btn.is-active { background: linear-gradient(130deg, #047C54 0%, #024732 100%); border-color: #047C54; color: #fff; }
        @media (max-width: 767.98px) {
            .ot-type-group { gap: 8px; }
            .ot-type-btn { gap: 5px; padding: 11px 8px; font-size: 12px; border-radius: 10px; }
            .ot-type-btn i { font-size: 16px; }
        }
    </style>
    <div class="page-hero green">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Pengajuan Lembur</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pengajuan Lembur</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Isi formulir berikut untuk mengajukan permohonan lembur</p>
        </div>
    </div>

    <form wire:submit="submit">
        <!-- General -->
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-circle-info" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">General</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                {{-- Jenis dulu (pilihan tombol, 2 jenis tetap), baru tanggal. --}}
                <div>
                    <label class="form-label">Jenis Lembur <span style="color:var(--danger-text);">*</span></label>
                    <div class="ot-type-group" role="radiogroup" aria-label="Jenis Lembur">
                        @foreach ($this->overtimeTypes as $type)
                            <button type="button" role="radio" aria-checked="{{ $overtime_type === $type->value ? 'true' : 'false' }}"
                                wire:click="$set('overtime_type', '{{ $type->value }}')"
                                class="ot-type-btn {{ $overtime_type === $type->value ? 'is-active' : '' }}">
                                <i class="fa-solid {{ $type->icon() }}"></i>
                                <span>{{ $type->label() }}</span>
                            </button>
                        @endforeach
                    </div>
                    @error('overtime_type') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Tanggal Lembur <span style="color:var(--danger-text);">*</span></label>
                    <input type="date" class="form-input" wire:model="date">
                    @error('date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Uraian Tugas -->
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-list-check" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Uraian Tugas</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach ($tasks as $index => $task)
                    <div class="task-row" wire:key="task-{{ $index }}">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="task-num">{{ $index + 1 }}</div>
                                <span style="font-size:13px;font-weight:600;color:var(--text-body);">Detail Tugas</span>
                            </div>
                            @if (count($tasks) > 1)
                                <button type="button" wire:click="removeTask({{ $index }})" style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted);border:none;background:none;cursor:pointer;font-family:inherit;font-weight:600;">
                                    <i class="fa-solid fa-trash-can"></i> Hapus
                                </button>
                            @endif
                        </div>
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            <div>
                                <label class="form-label">Deskripsi Tugas</label>
                                <textarea class="form-input" rows="2" placeholder="Masukkan detail tugas lembur..." wire:model="tasks.{{ $index }}.task_description"></textarea>
                            </div>
                            <div class="time-grid">
                                <div>
                                    <input type="time" class="form-input" wire:model="tasks.{{ $index }}.start_time">
                                    <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">Jam Mulai</p>
                                </div>
                                <div class="time-arrow"><i class="fa-solid fa-arrow-right"></i></div>
                                <div>
                                    <input type="time" class="form-input" wire:model="tasks.{{ $index }}.end_time">
                                    <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">Jam Selesai</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn-add" wire:click="addTask">
                <i class="fa-solid fa-plus" style="font-size:12px;"></i> Tambah Detail Tugas
            </button>
        </div>

        <!-- Lampiran -->
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-paperclip" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Lampiran</span>
            </div>
            <label class="upload-zone" style="display:block;">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px;color:var(--text-faint);margin-bottom:10px;display:block;"></i>
                <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 4px;">Klik untuk upload file</p>
                <p style="font-size:12px;color:var(--text-muted);margin:0;">PDF, JPG, PNG &middot; Maks. 10MB per file</p>
                <input type="file" style="display:none;" multiple accept=".pdf,.jpg,.jpeg,.png" wire:model="attachments">
            </label>
            @error('attachments.*') <p style="color:var(--danger-text);font-size:12px;margin-top:8px;">{{ $message }}</p> @enderror

            @if ($attachments)
                <div style="display:flex;flex-direction:column;gap:6px;margin-top:12px;">
                    @foreach ($attachments as $file)
                        <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-body);">
                            <i class="fa-solid fa-file" style="color:var(--text-faint);"></i> {{ $file->getClientOriginalName() }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <a href="{{ route('my-attendance-requests') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit" @disabled($submitted)>
                {{-- Sedang kirim (wire:loading) atau sudah sukses & menunggu pindah halaman ($submitted):
                teks "Diajukan..." + ikon berputar, tombol mati. Normal: ikon kirim + label. --}}
                @if ($submitted)
                    <i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Diajukan...
                @else
                    <span wire:loading.remove wire:target="submit"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan Lembur</span>
                    <span wire:loading wire:target="submit"><i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Diajukan...</span>
                @endif
            </button>
        </div>
    </form>
</div>
