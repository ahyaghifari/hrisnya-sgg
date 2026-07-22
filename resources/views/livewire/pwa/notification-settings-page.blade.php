<div class="p-4 md:p-6">
    <x-page-header title="Pengaturan Notifikasi" subtitle="Atur jenis notifikasi apa saja yang ingin Anda terima" back="{{ route('akun') }}" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:1px;background:var(--surface);border-radius:14px;box-shadow:var(--shadow-card);overflow:hidden;">
        @foreach (\App\Enum\NotificationTypeEnum::cases() as $case)
            <div style="display:flex;align-items:center;gap:14px;padding:16px 18px;{{ !$loop->last ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $case->label() }}</p>
                    <p style="font-size:11.5px;color:var(--text-muted);margin-top:3px;">{{ $case->description() }}</p>
                </div>
                <label class="custom-checkbox" style="--accent:#047C54;flex-shrink:0;">
                    <input type="checkbox" wire:model="selections.{{ $case->value }}">
                    <span class="box"><i class="fa-solid fa-check"></i></span>
                </label>
            </div>
        @endforeach
    </div>

    <div class="mt-4" style="display:flex;justify-content:flex-end;">
        <button type="button" wire:click="save" class="btn-primary">
            <i class="fa-solid fa-check"></i> Simpan
        </button>
    </div>
</div>
