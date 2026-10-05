<div class="form-page-wrap">
    <style>
        /* Pola sama dengan Jenis Lembur (submit-overtime-request.blade.php), warna teal khas pelatihan. */
        .tr-type-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .tr-type-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 16px 12px; border-radius: 12px; border: 1.5px solid var(--border); background: var(--surface); color: var(--text-heading); font-family: inherit; font-size: 13.5px; font-weight: 700; text-align: center; cursor: pointer; transition: background 0.12s ease, border-color 0.12s ease; }
        .tr-type-btn i { font-size: 22px; }
        .tr-type-btn:hover { background: var(--surface-alt); }
        .tr-type-btn.is-active { background: linear-gradient(130deg, #115e59 0%, #0d9488 100%); border-color: #0d9488; color: #fff; }
        @media (max-width: 767.98px) {
            .tr-type-group { gap: 8px; }
            .tr-type-btn { gap: 5px; padding: 11px 8px; font-size: 12px; border-radius: 10px; }
            .tr-type-btn i { font-size: 16px; }
        }
    </style>

    <div class="page-hero teal">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('pelatihan') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Pelatihan</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Pengajuan Pelatihan</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pengajuan Pelatihan</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">
                {{ $jenis === 'sudah'
                    ? 'Ajukan pelatihan yang sudah terlaksana dengan sertifikat dan jam pelaksanaan. HC/Kepegawaian akan memeriksa konversi ke JP.'
                    : 'Ajukan pelatihan di luar yang diadakan perusahaan (mis. pelatihan eksternal/seminar)' }}
            </p>
        </div>
    </div>

    <form wire:submit="submit">
        <div class="form-card">
            <label class="form-label">Jenis Pengajuan <span style="color:var(--danger-text);">*</span></label>
            <div class="tr-type-group" role="radiogroup" aria-label="Jenis Pengajuan">
                @foreach (['sudah' => ['Sudah Terlaksana', 'fa-certificate'], 'belum' => ['Belum Terlaksana', 'fa-hourglass-start']] as $key => [$text, $icon])
                    <button type="button" role="radio" aria-checked="{{ $jenis === $key ? 'true' : 'false' }}"
                        wire:click="$set('jenis', '{{ $key }}')"
                        class="tr-type-btn {{ $jenis === $key ? 'is-active' : '' }}">
                        <i class="fa-solid {{ $icon }}"></i>
                        <span>{{ $text }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-circle-info" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Detail Pelatihan</span>
            </div>

            <div style="margin-bottom:12px;">
                <label class="form-label">Judul Pelatihan <span style="color:var(--danger-text);">*</span></label>
                <input type="text" class="form-input" wire:model="title" placeholder="Mis. Workshop Manajemen Rumah Sakit">
                @error('title') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div style="margin-bottom:12px;">
                <label class="form-label">Penyelenggara</label>
                <input type="text" class="form-input" wire:model="organizer" placeholder="Mis. PERSI, Kemenkes, dll.">
                @error('organizer') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            @if ($jenis === 'sudah')
                <label class="form-label">Sertifikat <span style="color:var(--danger-text);">*</span></label>
                <label class="upload-zone" style="display:block;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px;color:var(--text-faint);margin-bottom:10px;display:block;"></i>
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 4px;">
                        {{ $certificate ? $certificate->getClientOriginalName() : 'Klik untuk upload sertifikat' }}
                    </p>
                    <p style="font-size:12px;color:var(--text-muted);margin:0;">PDF, JPG, PNG &middot; Maks. 10MB</p>
                    <input type="file" style="display:none;" accept=".pdf,.jpg,.jpeg,.png" wire:model="certificate">
                </label>
                @error('certificate') <p style="color:var(--danger-text);font-size:12px;margin-top:8px;">{{ $message }}</p> @enderror
            @else
                <div class="col2-grid" style="margin-bottom:12px;">
                    <div>
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-input" wire:model="start_date">
                        @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" class="form-input" wire:model="end_date">
                        @error('end_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="margin-bottom:12px;">
                    <label class="form-label">Estimasi Biaya (Rp)</label>
                    <input type="number" step="0.01" min="0" class="form-input" wire:model="estimated_cost" placeholder="0">
                    @error('estimated_cost') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Deskripsi/Alasan</label>
                    <textarea class="form-input" rows="4" placeholder="Jelaskan tujuan/manfaat pelatihan ini..." wire:model="description"></textarea>
                    @error('description') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        @if ($jenis === 'sudah')
            <div class="form-card">
                <div class="section-head">
                    <div class="section-icon"><i class="fa-solid fa-clock" style="color:var(--brand);font-size:12px;"></i></div>
                    <span class="section-title">Jam Pelaksanaan</span>
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">Tambahkan satu baris per hari. Pelatihan lebih dari 1 hari boleh punya jam berbeda-beda.</p>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    @foreach ($sessions as $i => $session)
                        <div wire:key="claim-session-{{ $i }}" style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                            <div style="flex:1;min-width:140px;">
                                <input type="date" class="form-input" wire:model.live="sessions.{{ $i }}.date">
                                <p style="font-size:11.5px;color:var(--text-muted);margin:4px 0 0;">Tanggal</p>
                                @error("sessions.$i.date") <p style="color:var(--danger-text);font-size:12px;margin-top:2px;">{{ $message }}</p> @enderror
                            </div>
                            <div style="flex:1;min-width:110px;">
                                <input type="time" class="form-input" wire:model.live="sessions.{{ $i }}.start_time">
                                <p style="font-size:11.5px;color:var(--text-muted);margin:4px 0 0;">Jam Mulai</p>
                                @error("sessions.$i.start_time") <p style="color:var(--danger-text);font-size:12px;margin-top:2px;">{{ $message }}</p> @enderror
                            </div>
                            <div style="flex:1;min-width:110px;">
                                <input type="time" class="form-input" wire:model.live="sessions.{{ $i }}.end_time">
                                <p style="font-size:11.5px;color:var(--text-muted);margin:4px 0 0;">Jam Selesai</p>
                                @error("sessions.$i.end_time") <p style="color:var(--danger-text);font-size:12px;margin-top:2px;">{{ $message }}</p> @enderror
                            </div>
                            @if (count($sessions) > 1)
                                <button type="button" class="btn-ghost" wire:click="removeSession({{ $i }})" title="Hapus" style="padding:8px 10px;"><i class="fa-solid fa-trash"></i></button>
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('sessions') <p style="color:var(--danger-text);font-size:12px;margin-top:8px;">{{ $message }}</p> @enderror

                <button type="button" class="btn-ghost" wire:click="addSession" style="margin-top:12px;"><i class="fa-solid fa-plus" style="font-size:11px;"></i> Tambah Hari</button>

                <div style="margin-top:16px;background:var(--surface-alt);border-radius:10px;padding:10px 14px;font-size:12.5px;color:var(--text-body);">
                    Total <strong>{{ intdiv($this->totalMinutes, 60) }} jam {{ $this->totalMinutes % 60 }} menit</strong>
                    &rarr; perkiraan <strong>{{ $this->previewJp }} JP</strong>
                    <span style="color:var(--text-muted);">(1 JP = {{ \App\Models\LMS\TrainingClaim::minutesPerJp() }} menit). Angka final ditetapkan HC/Kepegawaian.</span>
                </div>
            </div>
        @endif

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <a href="{{ route($jenis === 'sudah' ? 'pelatihan.klaim' : 'pelatihan.pengajuan-saya') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit,certificate"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan Pelatihan</button>
        </div>
    </form>
</div>
