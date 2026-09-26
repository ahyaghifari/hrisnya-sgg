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

    {{-- Aturan --}}
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0;">Aturan Poin</h3>
        </div>

        @forelse ($this->rules as $rule)
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px 16px;padding:10px 0;border-bottom:1px solid var(--border-soft);{{ $rule->is_active ? '' : 'opacity:.55;' }}">
                <div style="flex:1;min-width:200px;">
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $rule->name }}</p>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">
                        {{ $rule->type->getLabel() }} &middot;
                        @if ($rule->trigger_type === \App\Enum\MeritPoint\MeritPointTriggerType::AUTOMATIC)
                            Otomatis: {{ $rule->event?->getLabel() }}
                        @else
                            Manual
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

    @if ($showForm)
        <div class="modal-overlay" wire:click="closeForm">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">{{ $ruleId ? 'Ubah' : 'Tambah' }} Aturan</h3>
                <form wire:submit.prevent="save" style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Nama Aturan</label>
                        <input type="text" wire:model="name" class="form-input">
                        @error('name') <p style="color:var(--danger-text);font-size:11px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Tipe</label>
                        <select wire:model="type" class="form-input">
                            @foreach (\App\Enum\MeritPoint\MeritPointType::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->getLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Poin</label>
                        <input type="number" wire:model="points" class="form-input">
                        <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">Boleh negatif untuk Penalty (mis. -5).</p>
                        @error('points') <p style="color:var(--danger-text);font-size:11px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Cara Pemakaian</label>
                        <select wire:model.live="trigger_type" class="form-input">
                            @foreach (\App\Enum\MeritPoint\MeritPointTriggerType::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->getLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($trigger_type === 'automatic')
                        <div>
                            <label class="form-label">Kejadian Pemicu</label>
                            <select wire:model="event" class="form-input">
                                <option value="">-- Pilih --</option>
                                @foreach (\App\Enum\MeritPoint\MeritPointEvent::cases() as $e)
                                    <option value="{{ $e->value }}">{{ $e->getLabel() }}</option>
                                @endforeach
                            </select>
                            @error('event') <p style="color:var(--danger-text);font-size:11px;">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <label class="custom-checkbox" style="--accent:#7c3aed;">
                        <input type="checkbox" wire:model="is_active" value="1">
                        <span class="box"><i class="fa-solid fa-check"></i></span> Aktif
                    </label>
                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                        <button type="button" wire:click="closeForm" class="btn-secondary">Batal</button>
                        <button type="submit" class="btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
