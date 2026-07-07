<div>
<div class="mb-6" style="display:flex;align-items:center;justify-content:space-between;">
    <div>
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Ubah Kata Sandi</h1>
    </div>
    <a href="{{ route('pwa.akun') }}" wire:navigate style="font-size:13px;font-weight:600;color:#64748b;text-decoration:none;">
        <i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali
    </a>
</div>

<div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;">
    <form wire:submit="changePassword" style="display:flex;flex-direction:column;gap:14px;">
        <div>
            <label class="form-label">Kata Sandi Saat Ini</label>
            <input type="password" class="form-input" wire:model="current_password" autocomplete="current-password">
            @error('current_password') <p style="color:#ef4444;font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label">Kata Sandi Baru</label>
            <input type="password" class="form-input" wire:model="new_password" autocomplete="new-password">
            @error('new_password') <p style="color:#ef4444;font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label">Konfirmasi Kata Sandi Baru</label>
            <input type="password" class="form-input" wire:model="new_password_confirmation" autocomplete="new-password">
        </div>
        <button type="submit" class="btn-primary" style="justify-content:center;">
            <i class="fa-solid fa-key"></i> Simpan Kata Sandi
        </button>
    </form>
</div>
</div>
