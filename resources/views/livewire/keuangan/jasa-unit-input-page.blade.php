<div>
    <x-page-header title="Input Jasa Unit" />

    <div class="mt-4 doc-type-layout">
        @if (auth()->user()->canFilterByCompany())
        <div class="doc-type-nav">
            @foreach ($this->companies as $company)
                <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn {{ $companyId === $company->id ? 'is-active' : '' }}">
                    {{ $company->name }}
                </button>
            @endforeach
        </div>
        @endif

        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:16px;">
            @if ($this->companies->isEmpty())
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-muted);font-size:12.5px;">
                    <i class="fa-solid fa-circle-info"></i>
                    Belum ada perusahaan dengan komponen Jasa Unit aktif.
                    @can(\App\Enum\CustomPermissionEnum::LIHAT_KOMPONEN_GAJI->value)
                        Aktifkan dulu di halaman <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate style="color:#042c6c;font-weight:600;">Komponen Gaji</a>.
                    @endcan
                </div>
            @elseif ($this->periods->isEmpty())
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-muted);font-size:12.5px;">
                    <i class="fa-solid fa-circle-info"></i>
                    Belum ada Periode Gaji untuk perusahaan ini. Buat dulu di halaman <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate style="color:#042c6c;font-weight:600;">Periode Gaji</a>.
                </div>
            @else
                <div>
                    <label class="form-label">Periode Gaji</label>
                    <select class="form-input" style="max-width:320px;" wire:change="selectPeriod($event.target.value)">
                        @foreach ($this->periods as $period)
                            <option value="{{ $period->id }}" @selected($period->id === $periodId)>{{ $period->start_date->format('d M Y') }} - {{ $period->end_date->format('d M Y') }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px;">
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;"><i class="fa-solid fa-hospital-user"></i> Jasa Unit per Karyawan</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">
                        Pilih divisi, karyawan (difilter sesuai divisi itu), jasa (difilter sesuai divisi itu juga), lalu nominal — bisa tambah beberapa baris.
                        @can('viewAny', \App\Models\Payroll\JasaUnitType::class)
                            Kelola daftar jasa & divisinya di halaman <a href="{{ route('keuangan.jasa-unit-types') }}" wire:navigate style="color:#042c6c;font-weight:600;">Jasa Unit</a>.
                        @endcan
                    </p>

                    <div style="display:flex;flex-direction:column;gap:10px;">
                        @foreach ($rows as $i => $row)
                            @php
                                $rowEmployees = $this->employeesFor($row['org_structure_id']);
                                $jasaOptions = $this->jasaOptionsFor($row['org_structure_id']);
                            @endphp
                            <div wire:key="jasa-unit-row-{{ $row['id'] ?? 'new-' . $i }}" style="display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;">
                                <div style="flex:1;min-width:180px;">
                                    <select class="form-input" style="font-size:12.5px;padding:7px 10px;" wire:model.live="rows.{{ $i }}.org_structure_id">
                                        <option value="">-- Pilih Divisi --</option>
                                        @foreach ($this->divisions as $division)
                                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div style="flex:1;min-width:220px;">
                                    <select class="form-input" style="font-size:12.5px;padding:7px 10px;" wire:model="rows.{{ $i }}.user_id" @disabled($rowEmployees->isEmpty())>
                                        <option value="">{{ $rowEmployees->isEmpty() ? '-- Pilih divisi dulu --' : '-- Pilih Karyawan --' }}</option>
                                        @foreach ($rowEmployees as $user)
                                            <option value="{{ $user->id }}" @selected($row['user_id'] === $user->id)>{{ $user->name }} ({{ $user->nip }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div style="flex:1;min-width:220px;">
                                    <select class="form-input" style="font-size:12.5px;padding:7px 10px;" wire:model="rows.{{ $i }}.jasa_unit_type_id" @disabled($jasaOptions->isEmpty())>
                                        <option value="">{{ $jasaOptions->isEmpty() ? '-- Pilih divisi dulu --' : '-- Pilih Jasa --' }}</option>
                                        @foreach ($jasaOptions as $jasa)
                                            <option value="{{ $jasa->id }}" @selected($row['jasa_unit_type_id'] === $jasa->id)>{{ $jasa->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <input type="number" step="0.01" class="form-input" style="max-width:200px;font-size:12.5px;padding:7px 10px;" wire:model="rows.{{ $i }}.amount" placeholder="Nominal (Rp)">
                                @if ($this->canEdit)
                                    <button type="button" wire:click="removeRow({{ $i }})" style="border:none;background:none;color:#dc2626;cursor:pointer;padding:7px;"><i class="fa-solid fa-trash"></i></button>
                                @endif

                                <div style="flex-basis:100%;">
                                    @error("rows.{$i}.user_id") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                    @error("rows.{$i}.jasa_unit_type_id") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                    @error("rows.{$i}.amount") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endforeach
                        @if (empty($rows))
                            <p style="font-size:11.5px;color:var(--text-faint);">Belum ada baris. Klik "Tambah Baris" di bawah.</p>
                        @endif
                    </div>

                    <div style="display:flex;gap:10px;margin-top:14px;">
                        @if ($this->canEdit)
                            <button type="button" wire:click="addRow" class="btn-ghost" style="padding:9px 16px;font-size:12.5px;"><i class="fa-solid fa-plus"></i> Tambah Baris</button>
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="padding:9px 20px;font-size:12.5px;">Simpan</button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
