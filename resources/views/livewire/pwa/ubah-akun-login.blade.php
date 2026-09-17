<div>
<x-page-header title="Username & Password SSO" :back="route('akun')" />

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
    @if (! $ssoLinked)
        <div style="display:flex;gap:12px;align-items:flex-start;">
            <i class="fa-solid fa-circle-info" style="color:var(--info-soft-text);font-size:18px;margin-top:1px;"></i>
            <div>
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Belum punya akun SSO</p>
                <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                    Akun SSO tidak bisa dibuat sendiri dari halaman ini. Hubungi tim IT untuk dibuatkan akun SSO
                    terlebih dahulu - setelah itu Anda bisa ganti username &amp; password SSO sendiri di sini.
                </p>
            </div>
        </div>
    @else
        <form wire:submit="save" style="display:flex;flex-direction:column;gap:14px;">
            <div>
                <label class="form-label">Username</label>
                <input type="text" class="form-input" wire:model="username" autocomplete="username">
                <p style="font-size:11px;color:var(--text-faint);margin-top:4px;">Bisa diganti pakai NIP atau nama lain sesuai keinginan, tidak harus sama seperti sebelumnya.</p>
                @error('username') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <hr style="border:none;border-top:1px solid var(--border-soft);margin:2px 0;">

            <div>
                <label class="form-label">Password Baru (opsional)</label>
                <input type="password" class="form-input" wire:model="password" autocomplete="new-password">
                <p style="font-size:11px;color:var(--text-faint);margin-top:4px;">Kosongkan kalau tidak mau ganti password SSO.</p>
                @error('password') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Konfirmasi Password Baru</label>
                <input type="password" class="form-input" wire:model="password_confirmation" autocomplete="new-password">
            </div>

            <button type="submit" class="btn-primary" style="justify-content:center;">
                <i class="fa-solid fa-user-pen"></i> Simpan
            </button>
        </form>
    @endif
</div>
</div>
