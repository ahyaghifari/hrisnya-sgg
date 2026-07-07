<div class="form-page-wrap">
    <div class="page-hero">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            @unless (request()->routeIs('pwa.*'))
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                    <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(147,197,253,0.75);text-decoration:none;font-weight:500;">Dashboard</a>
                    <span style="color:rgba(147,197,253,0.4);">/</span>
                    <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Pengajuan Izin</span>
                </div>
            @endunless
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pengajuan Izin</h1>
            <p style="font-size:13px;color:rgba(147,197,253,0.8);margin:0;">Isi formulir berikut untuk mengajukan permohonan izin</p>
        </div>
    </div>

    <form wire:submit="submit">
        <!-- Keterangan Izin -->
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-clipboard-list" style="color:#042c6c;font-size:12px;"></i></div>
                <span class="section-title">Keterangan Izin</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:18px;">
                <div>
                    <label class="form-label">Jenis Izin <span style="color:#ef4444;">*</span></label>
                    <select class="form-input" wire:model="permission_type_id">
                        <option value="">Pilih jenis izin</option>
                        @foreach ($this->permissionTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('permission_type_id') <p style="color:#ef4444;font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Tanggal Izin <span style="color:#ef4444;">*</span></label>
                    <input type="date" class="form-input" wire:model="date">
                    @error('date') <p style="color:#ef4444;font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Jam Izin <span style="color:#ef4444;">*</span></label>
                    <div class="time-grid">
                        <div>
                            <input type="time" class="form-input" wire:model="start_time">
                            <p style="font-size:11.5px;color:#64748b;margin:5px 0 0;">Jam Mulai</p>
                            @error('start_time') <p style="color:#ef4444;font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                        <div class="time-arrow"><i class="fa-solid fa-arrow-right"></i></div>
                        <div>
                            <input type="time" class="form-input" wire:model="end_time">
                            <p style="font-size:11.5px;color:#64748b;margin:5px 0 0;">Jam Berakhir</p>
                            @error('end_time') <p style="color:#ef4444;font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                <div>
                    <label class="form-label">Keperluan Izin</label>
                    <textarea class="form-input" rows="3" placeholder="Masukkan keperluan izin..." wire:model="purpose"></textarea>
                </div>
                <div>
                    <label class="form-label">Lokasi Izin</label>
                    <input type="text" class="form-input" placeholder="Masukkan lokasi izin" wire:model="location">
                </div>
            </div>
        </div>

        <!-- Pengganti -->
        <div class="form-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div class="section-head" style="margin-bottom:0;">
                    <div class="section-icon"><i class="fa-solid fa-user-group" style="color:#042c6c;font-size:12px;"></i></div>
                    <span class="section-title">Pengganti Tugas</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" wire:click="toggleSubstitute">
                    <span style="font-size:12px;color:#64748b;font-weight:500;">Tambahkan</span>
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
                    <i class="fa-solid fa-user-plus" style="color:#cbd5e1;font-size:20px;"></i>
                    <p style="font-size:13px;color:#64748b;margin:0;">Aktifkan toggle untuk menambahkan pengganti tugas</p>
                </div>
            @endif
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <a href="{{ route(request()->routeIs('pwa.*') ? 'pwa.pengajuan' : 'dashboard') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan Izin</button>
        </div>
    </form>
</div>
