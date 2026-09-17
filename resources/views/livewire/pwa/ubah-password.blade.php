<div>
<x-page-header title="Ubah Password" :back="route('akun')" />

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
    <form wire:submit="changePassword" style="display:flex;flex-direction:column;gap:14px;">
        <div>
            <label class="form-label">Kata Sandi Saat Ini</label>
            <input type="password" class="form-input" wire:model="current_password" autocomplete="current-password">
            @error('current_password') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label">Kata Sandi Baru</label>
            <input type="password" class="form-input" wire:model="new_password" autocomplete="new-password">
            @error('new_password') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
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
