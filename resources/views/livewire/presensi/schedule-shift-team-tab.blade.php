<div>
    @unless ($schedule->use_teams)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
            <i class="fa-solid fa-people-group" style="font-size:22px;color:var(--text-faint);"></i>
            <p style="font-size:12.5px;color:var(--text-muted);margin:12px 0 4px;">Jadwal ini belum memakai Regu.</p>
            <p style="font-size:11px;color:var(--text-faint);margin:0 0 16px;">Kalau diaktifkan, karyawan bisa dikelompokkan jadi beberapa regu per bulan, dan tabel "Detail Penjadwalan" ikut dikelompokkan berdasarkan regu. Kalau tidak, tabel tetap urut nama seperti biasa.</p>
            @if ($this->canManage)
                <button type="button" wire:click="activateTeams" class="btn-primary">
                    <i class="fa-solid fa-power-off"></i> Aktifkan Regu
                </button>
            @endif
        </div>
    @else
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
            <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">Regu</h3>
            @if ($this->canManage)
                <div style="display:flex;gap:8px;">
                    <button type="button" wire:click="openCreateTeamModal" class="btn-primary" style="padding:6px 14px;font-size:11.5px;">
                        <i class="fa-solid fa-plus"></i> Tambah Regu
                    </button>
                    <button type="button" wire:click="deactivateTeams" wire:confirm="Nonaktifkan pemakaian Regu untuk jadwal ini? Data regu & keanggotaan yang sudah diisi TIDAK ikut terhapus, cuma tabel Detail Penjadwalan kembali urut nama." class="btn-ghost" style="padding:6px 14px;font-size:11.5px;color:#dc2626;">
                        <i class="fa-solid fa-xmark"></i> Nonaktifkan
                    </button>
                </div>
            @endif
        </div>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">Kelompokkan karyawan jadwal ini supaya lebih enak dilihat saat mengisi jadwal. Regu diatur per bulan — ganti regu di bulan berjalan/depan tidak mengubah bulan yang sudah lewat.</p>

        @if ($this->teams->isEmpty())
            <p style="font-size:12px;color:var(--text-faint);text-align:center;padding:16px;">Belum ada regu.</p>
        @else
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach ($this->teams as $team)
                    <div style="display:flex;align-items:center;gap:10px;border:1px solid var(--border-soft);border-radius:10px;padding:10px 12px;">
                        <span style="width:14px;height:14px;border-radius:50%;background:{{ $team->color ?? '#042c6c' }};flex-shrink:0;"></span>
                        <span style="font-size:12.5px;font-weight:600;color:var(--text-heading);flex:1;">{{ $team->name }}</span>
                        <span style="font-size:11px;color:var(--text-muted);">{{ $team->membersForPeriod($period)->count() }} anggota bulan ini</span>
                        @if ($this->canManage)
                            <button type="button" wire:click="openEditTeamModal('{{ $team->id }}')" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:12px;">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" wire:click="deleteTeam('{{ $team->id }}')" wire:confirm="Hapus regu {{ $team->name }}? Anggotanya jadi Tanpa Regu di semua bulan." style="background:none;border:none;color:var(--danger-text);cursor:pointer;font-size:12px;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
            <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">Anggota — {{ $this->periodLabel }}</h3>
            <div>
                <input type="month" wire:model.live="period" class="form-input" style="max-width:170px;font-size:12px;padding:6px 8px;">
            </div>
        </div>

        @if (! $this->canEditPeriod)
            <p style="font-size:11px;color:var(--text-faint);margin:0 0 12px;">Bulan ini sudah lewat — cuma pengelola penuh jadwal yang bisa ubah regu bulan yang sudah lewat.</p>
        @endif

        @if ($this->employees->isEmpty())
            <p style="font-size:12px;color:var(--text-faint);text-align:center;padding:16px;">Belum ada karyawan di jadwal ini.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <th style="text-align:left;padding:8px 10px;color:var(--text-muted);font-weight:600;">Karyawan</th>
                            <th style="text-align:left;padding:8px 10px;color:var(--text-muted);font-weight:600;">Regu Bulan Ini</th>
                            @if ($this->canManage)
                                <th style="text-align:left;padding:8px 10px;color:var(--text-muted);font-weight:600;">Pindah Ke</th>
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->employees as $user)
                            @php
                                $team = $this->membership[$user->id] ?? null;
                                // Latar baris ikut warna regu (alpha 22 hex ~= 13%) begitu karyawan dikasih
                                // regu - biar langsung kelihatan berkelompok tanpa perlu scan kolom "Regu Bulan Ini".
                                $rowBg = $team ? ($team->color ?? '#042c6c') . '22' : 'transparent';
                            @endphp
                            <tr style="border-bottom:1px solid var(--border-soft);background:{{ $rowBg }};">
                                <td style="padding:8px 10px;color:var(--text-body);">{{ $user->name }}</td>
                                <td style="padding:8px 10px;">
                                    @if ($team)
                                        <span style="display:inline-flex;align-items:center;gap:6px;">
                                            <span style="width:10px;height:10px;border-radius:50%;background:{{ $team->color ?? '#042c6c' }};"></span>
                                            {{ $team->name }}
                                        </span>
                                    @else
                                        <span style="color:var(--text-faint);">Tanpa Regu</span>
                                    @endif
                                </td>
                                @if ($this->canManage)
                                    <td style="padding:8px 10px;">
                                        <select wire:model="assignForm.{{ $user->id }}" class="form-input" style="font-size:12px;padding:6px 8px;min-width:140px;" @disabled(! $this->canEditPeriod)>
                                            <option value="">Tanpa Regu</option>
                                            @foreach ($this->teams as $t)
                                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="padding:8px 10px;">
                                        <button type="button" wire:click="assignMember('{{ $user->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11px;" @disabled(! $this->canEditPeriod)>Simpan</button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @endunless

    @if ($showTeamModal)
        <div class="modal-overlay" wire:click="closeTeamModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 16px;">{{ $editingTeamId ? 'Edit Regu' : 'Tambah Regu' }}</h3>

                <form wire:submit.prevent="saveTeam" style="display:flex;flex-direction:column;gap:14px;">
                    <div>
                        <label class="form-label">Nama Regu</label>
                        <input type="text" wire:model="teamFormName" class="form-input" placeholder="Mis. Regu A / Tim Pagi" autofocus>
                        @error('teamFormName') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Warna</label>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <input type="color" wire:model="teamFormColor" style="width:44px;height:38px;padding:2px;border-radius:8px;border:1px solid var(--border-soft);background:var(--surface);cursor:pointer;">
                            <span style="font-size:11.5px;color:var(--text-muted);">Dipakai buat penanda regu ini di tabel Anggota.</span>
                        </div>
                        @error('teamFormColor') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px;">
                        <button type="button" wire:click="closeTeamModal" class="btn-ghost" style="padding:7px 16px;font-size:12px;">Batal</button>
                        <button type="submit" class="btn-primary" style="padding:7px 16px;font-size:12px;">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
