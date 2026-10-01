<div class="form-page-wrap">
    <div class="page-hero gold">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Pengajuan Izin</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pengajuan Izin</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Isi formulir berikut untuk mengajukan permohonan izin</p>
        </div>
    </div>

    @if ($usage)
        <div style="background:var(--warn-soft-bg);border:1px solid var(--warn-soft-border);border-radius:14px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
            <div style="width:36px;height:36px;background:var(--warn-soft-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-circle-info" style="color:var(--warn-soft-text);font-size:14px;"></i>
            </div>
            <div>
                @if ($this->ketentuanMode)
                    <p style="font-size:12.5px;font-weight:700;color:var(--warn-soft-text);margin:0 0 6px;">Ketentuan {{ $this->groupLabel }}</p>
                    <p style="font-size:12px;color:var(--warn-soft-text);margin:0;line-height:1.55;">
                        @if ($usage['max'] > 0)
                            Berdasarkan ketentuan, {{ $this->groupLabel }} dapat diajukan paling banyak <strong>{{ $usage['max'] }} kali</strong> dalam satu periode ({{ $usage['period_start']->translatedFormat('d M') }} – {{ $usage['period_end']->translatedFormat('d M Y') }}).
                        @else
                            Berdasarkan ketentuan, jumlah pengajuan {{ $this->groupLabel }} tidak dibatasi.
                        @endif
                        Izin diberikan untuk keperluan yang mendesak. Gunakan secara bijaksana dan bertanggung jawab serta tetap berkoordinasi dengan atasan Anda.
                        @if ($usage['used'] > 0)
                            Anda telah mengajukan {{ $this->groupLabel }} sebanyak <strong>{{ $usage['used'] }} kali</strong> pada periode ini.
                        @else
                            Anda belum pernah mengajukan {{ $this->groupLabel }} pada periode ini.
                        @endif
                    </p>
                @else
                <p style="font-size:12.5px;font-weight:700;color:var(--warn-soft-text);margin:0 0 6px;">Kuota Izin Periode Ini</p>
                @if ($usage['max'] > 0)
                    {{-- flex-direction:column - .saldo-pill inline-flex, <br> tidak memecah baris di dalamnya. --}}
                    <span class="saldo-pill" style="background:var(--warn-soft-bg);border-color:var(--warn-soft-border);color:var(--warn-soft-text);flex-direction:column;align-items:flex-start;gap:2px;border-radius:12px;">
                        <span><i class="fa-solid fa-calendar-days" style="font-size:10px;"></i> Sisa <strong>{{ $usage['available'] }} hari</strong> dari {{ $usage['max'] }} hari</span>
                        <span style="font-weight:500;">Periode {{ $usage['period_start']->translatedFormat('d M') }} – {{ $usage['period_end']->translatedFormat('d M Y') }}</span>
                    </span>
                @else
                    <span style="font-size:11.5px;color:var(--text-muted);">Tidak dibatasi.</span>
                @endif
                @endif
            </div>
        </div>
    @endif

    <form wire:submit="submit">
        <!-- Keterangan Izin -->
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-clipboard-list" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Keterangan Izin</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:18px;">
                <div>
                    <label class="form-label">Kategori Izin <span style="color:var(--danger-text);">*</span></label>
                    <select class="form-input" wire:model.live="group">
                        <option value="">Pilih kategori izin</option>
                        @foreach ($this->kelompokOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('group') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                @if ($group && count($this->subJenisOptions) > 0)
                    <div>
                        <label class="form-label">Jenis Izin <span style="color:var(--danger-text);">*</span></label>
                        <select class="form-input" wire:model.live="permission_type">
                            <option value="">Pilih jenis izin</option>
                            @foreach ($this->subJenisOptions as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('permission_type') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        @if ($this->selectedType?->description())
                            <p style="font-size:12px;color:var(--text-muted);margin-top:6px;background:var(--surface-alt);border-radius:8px;padding:8px 10px;">
                                <i class="fa-solid fa-circle-info" style="margin-right:4px;"></i>{{ $this->selectedType->description() }}
                            </p>
                        @endif
                    </div>
                @endif

                @if ($group)
                    <div>
                        <label class="form-label">{{ in_array($group, ['sakit', 'khusus']) ? 'Tanggal Mulai' : 'Tanggal Izin' }} <span style="color:var(--danger-text);">*</span></label>
                        <input type="date" class="form-input" wire:model.live="date">
                        @error('date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if (in_array($group, ['sakit', 'khusus']))
                        <div>
                            <label class="form-label">Tanggal Selesai <span style="color:var(--danger-text);">*</span></label>
                            <input type="date" class="form-input" wire:model="end_date">
                            @error('end_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($group === 'meninggalkan_jam_kerja')
                        <div>
                            <label class="form-label">Jam Izin <span style="color:var(--danger-text);">*</span></label>
                            <div class="time-grid">
                                <div>
                                    <input type="time" class="form-input" wire:model="start_time">
                                    <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">Jam Mulai</p>
                                    @error('start_time') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                                </div>
                                <div class="time-arrow"><i class="fa-solid fa-arrow-right"></i></div>
                                <div>
                                    <input type="time" class="form-input" wire:model="end_time">
                                    <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">Jam Berakhir</p>
                                    @error('end_time') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="form-label">Keperluan Izin @if ($this->purposeRequired)<span style="color:var(--danger-text);">*</span>@endif</label>
                        <textarea class="form-input" rows="3" placeholder="Masukkan keperluan izin..." wire:model="purpose"></textarea>
                        @error('purpose') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Lokasi Izin</label>
                        <input type="text" class="form-input" placeholder="Masukkan lokasi izin" wire:model="location">
                    </div>
                @endif
            </div>
        </div>

        @if ($this->lampiranTersedia)
            <!-- Lampiran -->
            <div class="form-card">
                <div class="section-head">
                    <div class="section-icon"><i class="fa-solid fa-paperclip" style="color:var(--brand);font-size:12px;"></i></div>
                    <span class="section-title">Lampiran{{ $this->lampiranLabelSuffix }} @if ($this->lampiranWajib)<span style="color:var(--danger-text);">*</span>@endif</span>
                </div>
                <label class="upload-zone" style="display:block;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px;color:var(--text-faint);margin-bottom:10px;display:block;"></i>
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 4px;">Klik untuk upload file</p>
                    <p style="font-size:12px;color:var(--text-muted);margin:0;">PDF, JPG, PNG &middot; Maks. 10MB per file</p>
                    <input type="file" style="display:none;" multiple accept=".pdf,.jpg,.jpeg,.png" wire:model="attachments">
                </label>
                @error('attachments') <p style="color:var(--danger-text);font-size:12px;margin-top:8px;">{{ $message }}</p> @enderror
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
        @endif

        <!-- Pengganti -->
        <div class="form-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div class="section-head" style="margin-bottom:0;">
                    <div class="section-icon"><i class="fa-solid fa-user-group" style="color:var(--brand);font-size:12px;"></i></div>
                    <span class="section-title">Pengganti Tugas</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" wire:click="toggleSubstitute">
                    <span style="font-size:12px;color:var(--text-muted);font-weight:500;">Tambahkan</span>
                    <div class="toggle {{ $with_substitute ? 'on' : '' }}"></div>
                </div>
            </div>

            @if ($with_substitute)
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div>
                        <label class="form-label">Nama Pengganti</label>
                        <input type="text" class="form-input" placeholder="Nama karyawan pengganti" wire:model="substitute_user_name">
                    </div>
                    <div>
                        <label class="form-label">Tugas yang Diberikan</label>
                        <textarea class="form-input" rows="2" placeholder="Deskripsikan tugas yang dikerjakan pengganti..." wire:model="substitute_task"></textarea>
                    </div>
                </div>
            @else
                <div class="pengganti-placeholder">
                    <i class="fa-solid fa-user-plus" style="color:var(--text-faint);font-size:20px;"></i>
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Aktifkan toggle untuk menambahkan pengganti tugas</p>
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
                    <span wire:loading.remove wire:target="submit"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan Izin</span>
                    <span wire:loading wire:target="submit"><i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Diajukan...</span>
                @endif
            </button>
        </div>
    </form>
</div>
