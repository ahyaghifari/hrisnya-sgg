<div>
<x-page-header title="Merit Poin" subtitle="Rangkuman skor karyawan dan aturan poin (otomatis & manual)" />

@can('Create:MeritPointRule')
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <button type="button" wire:click="create" class="btn-primary"><i class="fa-solid fa-plus"></i> Tambah Aturan</button>
    </div>
@endcan

@if (auth()->user()->canFilterByCompany())
    <div class="mb-4" style="max-width:280px;">
        <label class="form-label">Perusahaan</label>
        <select wire:model.live="companyId" class="form-input">
            @foreach ($this->companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
    </div>
@endif

    @unless ($this->enabled)
        <div style="background:var(--surface);border-radius:12px;padding:14px 16px;margin-bottom:16px;font-size:13px;color:var(--text-muted);">
            Merit Poin belum diaktifkan untuk perusahaan ini. Aktifkan di Pengaturan Kepegawaian.
        </div>
    @endunless

    {{-- Rangkuman --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px;">
        @foreach ([
            ['Karyawan skor di bawah 100', $this->summary['karyawan'], 'var(--text-heading)'],
            ['Penalti bulan ini', $this->summary['penalti'], 'var(--danger-text)'],
            ['Reward bulan ini', '+' . $this->summary['reward'], '#15803d'],
        ] as [$label, $value, $color])
            <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:16px;">
                <p style="font-size:11.5px;color:var(--text-muted);margin:0;">{{ $label }}</p>
                <p style="font-size:24px;font-weight:800;margin:4px 0 0;color:{{ $color }};">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-bottom:16px;">
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Skor Terendah</h3>
            @forelse ($this->lowestBalances as $b)
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-soft);font-size:13px;">
                    <span>{{ $b->user?->name }}</span>
                    <strong>{{ $b->merit_point }}</strong>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada transaksi.</p>
            @endforelse
        </div>

        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Transaksi Terbaru</h3>
            @forelse ($this->recentTransactions as $t)
                <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-soft);font-size:13px;">
                    <span style="min-width:0;">
                        {{ $t->user?->name }}
                        <span style="display:block;font-size:11.5px;color:var(--text-muted);">{{ $t->reason }} &middot; {{ $t->created_at?->translatedFormat('d M Y') }}</span>
                    </span>
                    <strong style="color:{{ $t->points >= 0 ? '#15803d' : 'var(--danger-text)' }};">{{ $t->points >= 0 ? '+' : '' }}{{ $t->points }}</strong>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada transaksi.</p>
            @endforelse
        </div>
    </div>

    {{-- Aturan Poin: SELALU manual, diberikan admin lewat modal transaksi di tab Karir Profil
    Saya (izin BERI_MERIT_POINT). Otomatis punya section sendiri di bawah (4 baris tetap, tidak
    bisa ditambah/dihapus di sini). --}}
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0;">Aturan Poin</h3>
        </div>

        @forelse ($this->rules as $rule)
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px 16px;padding:10px 0;border-bottom:1px solid var(--border-soft);{{ $rule->is_active ? '' : 'opacity:.55;' }}">
                <div style="flex:1;min-width:200px;">
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $rule->name }}</p>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">
                        {{ $rule->type->getLabel() }}
                        @if ($rule->bonus_points !== null)
                            &middot; bonus {{ $rule->bonus_points >= 0 ? '+' : '' }}{{ $rule->bonus_points }} setelah 100
                        @endif
                    </p>
                </div>
                <strong style="color:{{ $rule->points >= 0 ? '#15803d' : 'var(--danger-text)' }};">{{ $rule->points >= 0 ? '+' : '' }}{{ $rule->points }}</strong>
                @if ($this->canManage)
                    <button type="button" wire:click="toggleActive('{{ $rule->id }}')" class="badge {{ $rule->is_active ? 'badge-approved' : 'badge-cancelled' }}" style="border:none;cursor:pointer;">{{ $rule->is_active ? 'Aktif' : 'Nonaktif' }}</button>
                    <button type="button" wire:click="edit('{{ $rule->id }}')" title="Ubah" style="background:none;border:none;color:var(--text-muted);cursor:pointer;"><i class="fa-solid fa-pen"></i></button>
                @endif
            </div>
        @empty
            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada aturan.</p>
        @endforelse
    </div>

    {{-- Otomatis: 4 baris TETAP, 1 per App\Enum\MeritPoint\MeritPointEvent - tidak bisa
    ditambah/dihapus, langsung dimuat semuanya (baris yang belum pernah diisi tampil nonaktif/0,
    bukan disembunyikan) supaya admin langsung lihat kejadian apa saja yang bisa disambungkan. --}}
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
        <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 2px;">Otomatis</h3>

        @foreach (\App\Enum\MeritPoint\MeritPointEvent::cases() as $event)
            @php $row = $automaticForm[$event->value]; @endphp
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:10px 0;border-bottom:1px solid var(--border-soft);{{ $row['is_active'] ? '' : 'opacity:.7;' }}">
                <div style="flex:1;min-width:160px;">
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $event->getLabel() }}</p>
                </div>

                @if ($this->canManage)
                    <select wire:model.live="automaticForm.{{ $event->value }}.type" class="form-input" style="max-width:140px;">
                        @foreach (\App\Enum\MeritPoint\MeritPointType::cases() as $t)
                            <option value="{{ $t->value }}">{{ $t->getLabel() }}</option>
                        @endforeach
                    </select>
                    <input type="number" wire:model="automaticForm.{{ $event->value }}.points" class="form-input" style="max-width:90px;" placeholder="Poin">
                    @if ($row['type'] === \App\Enum\MeritPoint\MeritPointType::REWARD->value)
                        <input type="number" wire:model="automaticForm.{{ $event->value }}.bonus_points" class="form-input" style="max-width:100px;" placeholder="Bonus setelah 100">
                    @endif
                    <div style="display:flex;align-items:center;gap:6px;">
                        <label class="custom-checkbox" style="--accent:#7c3aed;">
                            <input type="checkbox" wire:model="automaticForm.{{ $event->value }}.is_active">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span style="font-size:12px;color:var(--text-muted);">Aktif</span>
                    </div>
                    <button type="button" wire:click="saveAutomaticRule('{{ $event->value }}')" class="btn-secondary" style="font-size:12px;padding:6px 12px;">Simpan</button>
                    @error("automaticForm.{$event->value}.points") <p style="width:100%;color:var(--danger-text);font-size:11px;margin:0;">{{ $message }}</p> @enderror
                    @error("automaticForm.{$event->value}.bonus_points") <p style="width:100%;color:var(--danger-text);font-size:11px;margin:0;">{{ $message }}</p> @enderror
                @else
                    <span style="font-size:12.5px;color:var(--text-muted);">{{ $row['type'] }}</span>
                    <strong style="color:{{ $row['points'] >= 0 ? '#15803d' : 'var(--danger-text)' }};">{{ $row['points'] >= 0 ? '+' : '' }}{{ $row['points'] }}</strong>
                    @if ($row['type'] === \App\Enum\MeritPoint\MeritPointType::REWARD->value && $row['bonus_points'] !== '' && $row['bonus_points'] !== null)
                        <span style="font-size:11px;color:var(--text-muted);">(bonus {{ $row['bonus_points'] >= 0 ? '+' : '' }}{{ $row['bonus_points'] }} setelah 100)</span>
                    @endif
                    <span class="badge {{ $row['is_active'] ? 'badge-approved' : 'badge-cancelled' }}">{{ $row['is_active'] ? 'Aktif' : 'Nonaktif' }}</span>
                @endif
            </div>
        @endforeach
    </div>

    @if ($showForm)
        {{-- Pola pilihan tipe (2 tombol, ikon di atas label) sama persis submit-overtime-request.blade.php
        .ot-type-group - dipakai ulang di sini biar konsisten lintas halaman. --}}
        <style>
            .mp-type-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .mp-type-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 16px 12px; border-radius: 12px; border: 1.5px solid var(--border); background: var(--surface); color: var(--text-heading); font-family: inherit; font-size: 13.5px; font-weight: 700; text-align: center; cursor: pointer; transition: background 0.12s ease, border-color 0.12s ease; }
            .mp-type-btn i { font-size: 22px; }
            .mp-type-btn:hover { background: var(--surface-alt); }
            .mp-type-btn.is-active.mp-type-reward { background: linear-gradient(130deg, #16a34a 0%, #14532d 100%); border-color: #16a34a; color: #fff; }
            .mp-type-btn.is-active.mp-type-penalty { background: linear-gradient(130deg, #dc2626 0%, #7f1d1d 100%); border-color: #dc2626; color: #fff; }
        </style>
        <div class="modal-overlay" wire:click="closeForm">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">{{ $ruleId ? 'Ubah' : 'Tambah' }} Aturan</h3>
                <form wire:submit.prevent="save" style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Nama Aturan</label>
                        <input type="text" wire:model="name" class="form-input">
                        @error('name') <p style="color:var(--danger-text);font-size:11px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Tipe</label>
                        <div class="mp-type-group" role="radiogroup" aria-label="Tipe">
                            @foreach (\App\Enum\MeritPoint\MeritPointType::cases() as $t)
                                <button type="button" role="radio" aria-checked="{{ $type === $t->value ? 'true' : 'false' }}"
                                    wire:click="$set('type', '{{ $t->value }}')"
                                    class="mp-type-btn mp-type-{{ $t->value }} {{ $type === $t->value ? 'is-active' : '' }}">
                                    <i class="fa-solid {{ $t === \App\Enum\MeritPoint\MeritPointType::REWARD ? 'fa-award' : 'fa-triangle-exclamation' }}"></i>
                                    <span>{{ $t->getLabel() }}</span>
                                </button>
                            @endforeach
                        </div>
                        @error('type') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Poin</label>
                        <input type="number" wire:model="points" class="form-input">
                        @error('points') <p style="color:var(--danger-text);font-size:11px;">{{ $message }}</p> @enderror
                    </div>
                    @if ($type === \App\Enum\MeritPoint\MeritPointType::REWARD->value)
                        <div>
                            <label class="form-label">Poin Bonus</label>
                            <input type="number" wire:model="bonus_points" class="form-input" placeholder="Kosongkan kalau tidak ada bonus">
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">Dipakai ganti "Poin" begitu saldo karyawan sudah 100+, biasanya lebih kecil.</p>
                            @error('bonus_points') <p style="color:var(--danger-text);font-size:11px;">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div style="display:flex;align-items:center;gap:8px;">
                        <label class="custom-checkbox" style="--accent:#7c3aed;">
                            <input type="checkbox" wire:model="is_active">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span style="font-size:13px;color:var(--text-body);">Aktif</span>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                        <button type="button" wire:click="closeForm" class="btn-secondary">Batal</button>
                        <button type="submit" class="btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
