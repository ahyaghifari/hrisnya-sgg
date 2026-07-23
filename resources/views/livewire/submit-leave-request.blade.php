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

    @if ($this->tenureWarning)
        <div style="background:var(--danger-soft-bg);border:1px solid var(--danger-soft-border);border-radius:14px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
            <div style="width:36px;height:36px;background:var(--danger-soft-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger-soft-text);font-size:14px;"></i>
            </div>
            <p style="font-size:12.5px;font-weight:600;color:var(--danger-soft-text);margin:0;">{{ $this->tenureWarning }}</p>
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

    <form wire:submit="submit">
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-clipboard-list" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Keterangan Cuti</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:18px;">
                <div>
                    <label class="form-label">Jenis Cuti <span style="color:var(--danger-text);">*</span></label>
                    <select class="form-input" wire:model.live="leave_type_id">
                        <option value="">Pilih jenis cuti</option>
                        @foreach ($this->leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('leave_type_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div class="toggle-wrapper" wire:click="toggleOneDay">
                    <div class="toggle {{ ! $is_one_day ? 'on' : '' }}"></div>
                    <div>
                        <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 2px;">Aktifkan jika cuti lebih dari 1 hari</p>
                        <p style="font-size:12px;color:var(--text-muted);margin:0;">Pilih tanggal mulai dan tanggal selesai</p>
                    </div>
                </div>

                @if ($is_one_day)
                    <div>
                        <label class="form-label">Tanggal Cuti <span style="color:var(--danger-text);">*</span></label>
                        <input type="date" class="form-input" wire:model.live="start_date">
                        @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label class="form-label">Tanggal Cuti <span style="color:var(--danger-text);">*</span></label>
                        <div class="date-grid">
                            <div>
                                <input type="date" class="form-input" wire:model.live="start_date">
                                <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">Tanggal Mulai</p>
                            </div>
                            <div>
                                <input type="date" class="form-input" wire:model="end_date">
                                <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">Tanggal Selesai</p>
                                @error('end_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <div>
                    <label class="form-label">Alasan / Keterangan</label>
                    <textarea class="form-input" rows="3" placeholder="Tambahkan keterangan (opsional)" wire:model="reason"></textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
            <a href="{{ route('my-attendance-requests') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan Cuti</button>
        </div>
    </form>
</div>
