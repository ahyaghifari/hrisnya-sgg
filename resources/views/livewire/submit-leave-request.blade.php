<div class="form-page-wrap">
    <div class="page-hero">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);font-size:11px;">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.75);font-weight:500;">Pengajuan</span>
                <span style="color:rgba(255,255,255,0.4);font-size:11px;">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Cuti</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pengajuan Cuti</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Isi formulir berikut untuk mengajukan permohonan cuti</p>
        </div>
    </div>

    @if ($this->minimumTenureNote)
        <div style="background:var(--danger-soft-bg);border:1px solid var(--danger-soft-border);border-radius:14px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
            <div style="width:36px;height:36px;background:var(--danger-soft-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger-soft-text);font-size:14px;"></i>
            </div>
            <p style="font-size:12.5px;font-weight:600;color:var(--danger-soft-text);margin:0;">{{ $this->minimumTenureNote }}</p>
        </div>
    @endif

    @if ($this->cutiTahunanClosedNote)
        <div style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:14px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
            <div style="width:36px;height:36px;background:var(--info-soft-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-circle-info" style="color:var(--info-soft-text);font-size:14px;"></i>
            </div>
            <p style="font-size:12.5px;font-weight:600;color:var(--info-soft-text);margin:0;">{{ $this->cutiTahunanClosedNote }}</p>
        </div>
    @endif

    @if ($balance)
        <div style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:14px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
            <div style="width:36px;height:36px;background:var(--info-soft-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-circle-info" style="color:var(--info-soft-text);font-size:14px;"></i>
            </div>
            <div>
                <p style="font-size:12.5px;font-weight:700;color:var(--info-soft-text);margin:0 0 6px;">Saldo Cuti Tersedia</p>
                <span class="saldo-pill"><i class="fa-solid fa-sun" style="font-size:10px;"></i> Sisa <strong>{{ $balance['available'] }} hari</strong> dari {{ $balance['quota'] }} hari (tahun ini)</span>

                @if ($periode)
                    <div style="margin-top:8px;">
                        @if ($periode['max'] > 0)
                            <span class="saldo-pill"><i class="fa-solid fa-calendar-days" style="font-size:10px;"></i> Sisa <strong>{{ $periode['available'] }} hari</strong> dari {{ $periode['max'] }} hari (periode {{ $periode['period_start']->translatedFormat('d M') }} – {{ $periode['period_end']->translatedFormat('d M Y') }})</span>
                        @else
                            <span style="font-size:11.5px;color:var(--text-muted);">Batas per periode: tidak dibatasi.</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    @unless ($this->minimumTenureNote)
    <form wire:submit="submit">
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-clipboard-list" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Keterangan Cuti</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:18px;">
                <div>
                    <label class="form-label">Jenis Cuti <span style="color:var(--danger-text);">*</span></label>
                    <select class="form-input" wire:model.live="leave_type">
                        <option value="">Pilih jenis cuti</option>
                        @foreach ($this->leaveTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('leave_type') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Tanggal Cuti <span style="color:var(--danger-text);">*</span></label>
                    <div class="date-grid">
                        <div>
                            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 5px;">Tanggal Mulai</p>
                            <input type="date" class="form-input" wire:model.live="start_date">
                            @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 5px;">Tanggal Selesai</p>
                            <input type="date" class="form-input" wire:model.live="end_date">
                            @error('end_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:6px 0 0;">1 hari cuti? Isi tanggal mulai dan selesai sama.</p>
                </div>

                @if ($this->workingDays !== null)
                    <div style="background:var(--surface-alt);border-radius:10px;padding:10px 14px;font-size:12.5px;color:var(--text-body);">
                        <i class="fa-solid fa-calendar-check" style="margin-right:6px;color:var(--brand);"></i>
                        @if ($this->selectedCategory?->countsCalendarDays())
                            Total <strong>{{ $this->workingDays }} hari kalender</strong>
                        @else
                            Total <strong>{{ $this->workingDays }} hari kerja</strong> (hari libur sesuai jadwal tidak dihitung)
                        @endif
                    </div>
                @endif

                @if ($this->requiresAttachment)
                    <div>
                        <label class="form-label">Lampiran Surat Dokter / Bidan <span style="color:var(--danger-text);">*</span></label>
                        <input type="file" class="form-input" wire:model="attachments" multiple accept=".pdf,.jpg,.jpeg,.png">
                        <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">PDF/JPG/PNG, maks 10 MB per file.</p>
                        <div wire:loading wire:target="attachments" style="font-size:12px;color:var(--text-muted);margin-top:4px;">Mengunggah…</div>
                        @error('attachments') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        @error('attachments.*') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="form-label">Alasan / Keterangan @if ($this->reasonRequired)<span style="color:var(--danger-text);">*</span>@endif</label>
                    <textarea class="form-input" rows="3" placeholder="{{ $this->reasonRequired ? 'Tambahkan keterangan' : 'Tambahkan keterangan (opsional)' }}" wire:model="reason"></textarea>
                    @error('reason') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
            <a href="{{ route('my-attendance-requests') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit" @disabled($submitted)>
                {{-- Sedang kirim (wire:loading) atau sudah sukses & menunggu pindah halaman ($submitted):
                teks "Diajukan..." + ikon berputar, tombol mati. Normal: ikon kirim + label. --}}
                @if ($submitted)
                    <i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Diajukan...
                @else
                    <span wire:loading.remove wire:target="submit"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan Cuti</span>
                    <span wire:loading wire:target="submit"><i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Diajukan...</span>
                @endif
            </button>
        </div>
    </form>
    @endunless
</div>
