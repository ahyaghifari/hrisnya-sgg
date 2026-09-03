<div class="p-4 md:p-6">
    <x-page-header title="Pengaturan Email Slip Gaji" />

    <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;max-width:640px;">
        Akun SMTP per perusahaan untuk mengirim slip gaji lewat email. Perusahaan yang belum diatur
        (atau dinonaktifkan) akan tetap terkirim lewat email server default sistem.
    </p>

    <div class="mt-4">
        <div class="doc-type-layout">
            @if (auth()->user()->canFilterByCompany())
            <div class="doc-type-nav">
                @foreach ($this->companies as $company)
                    <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn doc-type-btn-sky {{ $companyId === $company->id ? 'is-active' : '' }}">
                        <i class="fa-solid fa-building"></i>
                        <span>{{ $company->name }}</span>
                    </button>
                @endforeach
            </div>
            @endif

            <div style="flex:1;min-width:0;">
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;max-width:560px;">
                    <div style="display:grid;gap:14px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;">
                            <input type="checkbox" wire:model="isActive">
                            Aktifkan pengiriman lewat akun ini
                        </label>

                        <div>
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-input" wire:model="host" placeholder="smtp.gmail.com">
                            @error('host') <p class="text-danger" style="font-size:11px;margin-top:3px;">{{ $message }}</p> @enderror
                        </div>

                        <div style="display:flex;gap:12px;">
                            <div style="flex:1;">
                                <label class="form-label">Port</label>
                                <input type="number" class="form-input" wire:model="port">
                                @error('port') <p class="text-danger" style="font-size:11px;margin-top:3px;">{{ $message }}</p> @enderror
                            </div>
                            <div style="flex:1;">
                                <label class="form-label">Enkripsi</label>
                                <select class="form-input" wire:model="encryption">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="">Tidak ada</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Username</label>
                            <input type="text" class="form-input" wire:model="username" autocomplete="off" placeholder="hrd.barabai@syifamedika.co.id">
                            @error('username') <p class="text-danger" style="font-size:11px;margin-top:3px;">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="form-label">Password / App Password</label>
                            <input type="password" class="form-input" wire:model="password" autocomplete="new-password">
                            <p style="font-size:11px;color:var(--text-muted);margin:3px 0 0;">Disimpan terenkripsi. Untuk Gmail pakai <em>App Password</em>, bukan password akun.</p>
                            @error('password') <p class="text-danger" style="font-size:11px;margin-top:3px;">{{ $message }}</p> @enderror
                        </div>

                        <div style="display:flex;gap:12px;">
                            <div style="flex:1;">
                                <label class="form-label">Email Pengirim (From)</label>
                                <input type="email" class="form-input" wire:model="fromAddress" placeholder="hrd.barabai@syifamedika.co.id">
                                @error('fromAddress') <p class="text-danger" style="font-size:11px;margin-top:3px;">{{ $message }}</p> @enderror
                            </div>
                            <div style="flex:1;">
                                <label class="form-label">Nama Pengirim</label>
                                <input type="text" class="form-input" wire:model="fromName" placeholder="HRD RSU Syifa Barabai">
                                @error('fromName') <p class="text-danger" style="font-size:11px;margin-top:3px;">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div style="display:flex;gap:10px;align-items:center;margin-top:4px;">
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="padding:9px 20px;">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan
                            </button>
                        </div>

                        <div style="border-top:1px solid var(--border-soft);padding-top:14px;margin-top:4px;">
                            <label class="form-label">Kirim email tes ke</label>
                            <div style="display:flex;gap:10px;">
                                <input type="email" class="form-input" wire:model="testRecipient" style="flex:1;">
                                <button type="button" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest" class="btn-secondary" style="white-space:nowrap;padding:9px 16px;">
                                    <i class="fa-solid fa-paper-plane"></i> Kirim Tes
                                </button>
                            </div>
                            @if ($lastTestedAt)
                                <p style="font-size:11px;margin:6px 0 0;color:{{ $lastTestError ? 'var(--danger-text)' : 'var(--text-muted)' }};">
                                    Tes terakhir: {{ $lastTestedAt }} —
                                    {{ $lastTestError ? 'GAGAL: ' . $lastTestError : 'berhasil' }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
