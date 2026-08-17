<div>
    <x-page-header title="Aturan Extra Shift" />

    <div style="margin-bottom:16px;">
        <x-company-filter wire:model.live="companyId" :companies="$this->companies" />
    </div>

    <div class="card-surface-flush">
        <div style="padding:18px 20px 14px;border-bottom:1px solid var(--border-soft);">
            <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Pasangan Transisi</h4>
            <p style="font-size:11.5px;color:var(--text-muted);margin:0;">
                Transisi yang tidak ada di daftar ini otomatis TIDAK BOLEH diajukan. Nominal per pasangan diatur belakangan di halaman
                <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate style="color:#042c6c;font-weight:600;">Mapping Komponen Gaji</a> (Payroll), bukan di sini.
            </p>
        </div>

        @if ($this->canCreate)
            <div style="padding:16px 20px;border-bottom:1px solid var(--border-soft);display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;">
                <div>
                    <label class="form-label">Dari Shift</label>
                    <select class="form-input" wire:model="fromShiftType" style="min-width:160px;">
                        <option value="">-- Pilih --</option>
                        @foreach ($this->shiftTypeOptions as $type)
                            <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                        @endforeach
                    </select>
                    @error('fromShiftType') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <i class="fa-solid fa-arrow-right" style="color:var(--text-faint);margin-bottom:10px;"></i>
                <div>
                    <label class="form-label">Ke Shift</label>
                    <select class="form-input" wire:model="toShiftType" style="min-width:160px;">
                        <option value="">-- Pilih --</option>
                        @foreach ($this->shiftTypeOptions as $type)
                            <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                        @endforeach
                    </select>
                    @error('toShiftType') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="addRule" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-plus"></i> Tambah Pasangan
                </button>
            </div>
        @endif

        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transisi</th>
                        <th>Nominal</th>
                        @if ($this->canDelete)
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rules as $rule)
                        <tr wire:key="extra-shift-rule-{{ $rule->id }}">
                            <td>{{ $rule->from_shift_type_label }} <i class="fa-solid fa-arrow-right" style="font-size:10px;color:var(--text-faint);"></i> {{ $rule->to_shift_type_label }}</td>
                            <td>
                                @if ($rule->amount === null)
                                    <span class="badge badge-pending">Nominal belum diatur</span>
                                @else
                                    Rp {{ number_format($rule->amount, 0, ',', '.') }}
                                @endif
                            </td>
                            @if ($this->canDelete)
                                <td>
                                    <button type="button" wire:click="removeRule('{{ $rule->id }}')" wire:confirm="Hapus pasangan transisi ini?" style="background:none;border:none;color:var(--danger-text);cursor:pointer;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-sm text-slate-500 py-6">Belum ada pasangan transisi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
