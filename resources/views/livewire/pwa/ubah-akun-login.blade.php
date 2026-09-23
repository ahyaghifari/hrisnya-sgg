<div>
<x-page-header title="Password SSO" :back="route('akun')" />

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
    @if (! $ssoLinked)
        <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:16px;">
            <i class="fa-solid fa-circle-info" style="color:var(--info-soft-text);font-size:18px;margin-top:1px;"></i>
            <div>
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Belum punya akun SSO</p>
                <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                    Set password di bawah buat langsung bikin akun SSO. Username otomatis pakai NIP Anda (<strong>{{ $nip }}</strong>).
                </p>
            </div>
        </div>
    @endif

    <form wire:submit="save" style="display:flex;flex-direction:column;gap:14px;">
        <div>
            <label class="form-label">Username</label>
            <input type="text" class="form-input" value="{{ $nip }}" disabled style="opacity:.6;">
            <p style="font-size:11px;color:var(--text-faint);margin-top:4px;">Username SSO dibakukan pakai NIP, tidak bisa diubah.</p>
        </div>

        <hr style="border:none;border-top:1px solid var(--border-soft);margin:2px 0;">

        <div>
            <label class="form-label">Password</label>
            <div style="display:flex;gap:16px;margin-bottom:10px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;">
                    <input type="radio" wire:model.live="passwordMode" value="same_as_hris">
                    Sama dengan password HRIS
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;">
                    <input type="radio" wire:model.live="passwordMode" value="custom">
                    Password khusus SSO
                </label>
            </div>

            @if ($passwordMode === 'same_as_hris')
                <input type="password" class="form-input" wire:model="hrisPassword" autocomplete="current-password" placeholder="Ketik password HRIS Anda">
                <p style="font-size:11px;color:var(--text-faint);margin-top:4px;">Dipakai buat verifikasi sekaligus jadi password SSO — password HRIS Anda sendiri tidak ikut berubah.</p>
                @error('hrisPassword') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            @else
                <input type="password" class="form-input" wire:model="password" autocomplete="new-password" placeholder="Password baru untuk SSO">
                @error('password') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror

                <div style="margin-top:10px;">
                    <label class="form-label">Konfirmasi Password</label>
                    <input type="password" class="form-input" wire:model="password_confirmation" autocomplete="new-password">
                </div>
            @endif
        </div>

        <button type="submit" class="btn-primary" style="justify-content:center;">
            <i class="fa-solid fa-key"></i> {{ $ssoLinked ? 'Simpan' : 'Buat Akun SSO' }}
        </button>
    </form>
</div>
</div>
