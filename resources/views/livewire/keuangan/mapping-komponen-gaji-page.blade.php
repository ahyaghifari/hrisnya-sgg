@php
    $accentPalette = [
        ['solid' => '#042c6c', 'soft' => 'rgba(4,44,108,0.07)'],
        ['solid' => '#047C54', 'soft' => 'rgba(4,124,84,0.07)'],
        ['solid' => '#d97706', 'soft' => 'rgba(217,119,6,0.07)'],
        ['solid' => '#7c3aed', 'soft' => 'rgba(124,58,237,0.07)'],
        ['solid' => '#0f766e', 'soft' => 'rgba(15,118,110,0.07)'],
        ['solid' => '#4f46e5', 'soft' => 'rgba(79,70,229,0.07)'],
        ['solid' => '#dc2626', 'soft' => 'rgba(220,38,38,0.07)'],
    ];
@endphp

<div class="p-4 md:p-6">
    <x-page-header title="Mapping Komponen Gaji" />

    <div class="mt-4 doc-type-layout">
        <div class="doc-type-nav">
            @foreach ($this->companies as $company)
                <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn {{ $companyId === $company->id ? 'is-active' : '' }}">
                    {{ $company->name }}
                </button>
            @endforeach
        </div>

        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:16px;">
            @if (empty($this->activeComponents))
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-muted);font-size:12.5px;">
                    <i class="fa-solid fa-circle-info"></i>
                    Belum ada komponen gaji yang aktif untuk perusahaan ini. Aktifkan dulu di halaman <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate style="color:#042c6c;font-weight:600;">Komponen Gaji</a>.
                </div>
            @else
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    @foreach ($this->activeComponents as $i => $item)
                        @php $accent = $accentPalette[$i % count($accentPalette)]; @endphp
                        <button type="button" wire:click="selectComponent('{{ $item['key'] }}')"
                            style="display:flex;align-items:center;gap:7px;padding:9px 14px;border-radius:10px;border:1.5px solid {{ $activeComponent === $item['key'] ? $accent['solid'] : 'transparent' }};background:{{ $activeComponent === $item['key'] ? $accent['solid'] : $accent['soft'] }};color:{{ $activeComponent === $item['key'] ? '#fff' : $accent['solid'] }};font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;">
                            <i class="fa-solid {{ $item['icon'] }}"></i> {{ $item['label'] }}
                        </button>
                    @endforeach
                </div>

                @if ($activeComponent)
                    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px;">
                        {{-- Gaji Pokok --}}
                        @if ($activeComponent === 'gaji_pokok')
                            <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Gaji pokok per pangkat = Base Salary x (1 + persen kenaikan per kelas x langkah kelas). Langkah kelas dihitung dari "Kelas Mulai" pangkat ini, mentok di "Kelas Maksimal". Persen kenaikannya sendiri berlaku 1 nilai untuk semua pangkat di company ini — atur di halaman Komponen Gaji.</p>
                            <div style="display:flex;flex-direction:column;gap:14px;">
                                @foreach ($this->pangkats as $p)
                                    <div style="border:1px solid var(--border-soft);border-radius:10px;padding:10px 12px;">
                                        <p style="font-size:12.5px;font-weight:700;margin:0 0 8px;">{{ $p->name }}</p>
                                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
                                            <div>
                                                <label class="form-label" style="font-size:10.5px;">Base Salary (Rp)</label>
                                                <input type="number" step="0.01" class="form-input" style="font-size:12.5px;padding:7px 10px;" wire:model="gajiPokokValues.{{ $p->id }}" placeholder="Belum diisi">
                                                @error("gajiPokokValues.{$p->id}") <p style="font-size:10.5px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size:10.5px;">Kelas Mulai</label>
                                                <select class="form-input" style="font-size:12.5px;padding:7px 10px;" wire:model="startKelasIdValues.{{ $p->id }}">
                                                    <option value="">Belum diisi</option>
                                                    @foreach ($this->kelasList as $k)
                                                        @continue($k->level === null)
                                                        <option value="{{ $k->id }}">{{ $k->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error("startKelasIdValues.{$p->id}") <p style="font-size:10.5px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size:10.5px;">Kelas Maksimal</label>
                                                <select class="form-input" style="font-size:12.5px;padding:7px 10px;" wire:model="maxKelasIdValues.{{ $p->id }}">
                                                    <option value="">Tanpa batas</option>
                                                    @foreach ($this->kelasList as $k)
                                                        @continue($k->level === null)
                                                        <option value="{{ $k->id }}">{{ $k->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error("maxKelasIdValues.{$p->id}") <p style="font-size:10.5px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                @if ($this->pangkats->isEmpty())
                                    <p style="font-size:11.5px;color:var(--text-faint);">Belum ada data Pangkat untuk perusahaan ini.</p>
                                @endif
                            </div>
                            <button type="button" wire:click="saveGajiPokok" wire:loading.attr="disabled" wire:target="saveGajiPokok" class="btn-primary" style="margin-top:14px;padding:9px 20px;font-size:12.5px;">Simpan</button>
                        @endif

                        {{-- BPJS --}}
                        @if ($activeComponent === 'bpjs')
                            <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Basis potongan BPJS per pangkat. Pangkat yang dibiarkan "Belum dipetakan" otomatis TIDAK kena potongan BPJS sama sekali (bukan fallback UMP).</p>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                @foreach ($this->pangkats as $p)
                                    <div>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <span style="flex:1;font-size:12.5px;font-weight:600;">{{ $p->name }}</span>
                                            <select class="form-input" style="max-width:260px;font-size:12.5px;padding:7px 10px;" wire:model="bpjsValues.{{ $p->id }}">
                                                <option value="">Belum dipetakan (tidak ada potongan)</option>
                                                <option value="ump">UMP</option>
                                                <option value="declared_value">Nilai Tetap Perusahaan</option>
                                            </select>
                                        </div>
                                        @error("bpjsValues.{$p->id}") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;text-align:right;">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                                @if ($this->pangkats->isEmpty())
                                    <p style="font-size:11.5px;color:var(--text-faint);">Belum ada data Pangkat untuk perusahaan ini.</p>
                                @endif
                            </div>
                            <button type="button" wire:click="saveBpjs" wire:loading.attr="disabled" wire:target="saveBpjs" class="btn-primary" style="margin-top:14px;padding:9px 20px;font-size:12.5px;">Simpan</button>
                        @endif

                        {{-- Tunjangan Transportasi --}}
                        @if ($activeComponent === 'tunjangan_transportasi')
                            <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Nominal tunjangan transportasi per kelas.</p>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                @foreach ($this->kelasList as $k)
                                    <div>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <span style="flex:1;font-size:12.5px;font-weight:600;">{{ $k->name }}</span>
                                            <input type="number" step="0.01" class="form-input" style="max-width:220px;font-size:12.5px;padding:7px 10px;" wire:model="transportValues.{{ $k->id }}" placeholder="Belum diisi">
                                        </div>
                                        @error("transportValues.{$k->id}") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;text-align:right;">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                                @if ($this->kelasList->isEmpty())
                                    <p style="font-size:11.5px;color:var(--text-faint);">Belum ada data Kelas untuk perusahaan ini.</p>
                                @endif
                            </div>
                            <button type="button" wire:click="saveTransport" wire:loading.attr="disabled" wire:target="saveTransport" class="btn-primary" style="margin-top:14px;padding:9px 20px;font-size:12.5px;">Simpan</button>
                        @endif

                        {{-- Tunjangan Jabatan (matrix pangkat x kelas) --}}
                        @if ($activeComponent === 'tunjangan_jabatan')
                            <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Matriks nominal tunjangan jabatan — 1 nilai per kombinasi Pangkat x Kelas. Kombinasi yang dikosongkan tidak menghasilkan tunjangan.</p>
                            @if ($this->pangkats->isEmpty() || $this->kelasList->isEmpty())
                                <p style="font-size:11.5px;color:var(--text-faint);">Butuh data Pangkat DAN Kelas untuk perusahaan ini dulu.</p>
                            @else
                                <div style="overflow-x:auto;">
                                    <table style="border-collapse:collapse;width:100%;font-size:11.5px;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:6px 10px;border-bottom:1.5px solid var(--border);white-space:nowrap;position:sticky;left:0;background:var(--surface);z-index:1;border-right:1.5px solid var(--border);">Pangkat \ Kelas</th>
                                                @foreach ($this->kelasList as $k)
                                                    <th style="text-align:left;padding:6px 10px;border-bottom:1.5px solid var(--border);white-space:nowrap;">{{ $k->name }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($this->pangkats as $p)
                                                <tr>
                                                    <td style="padding:6px 10px;font-weight:600;white-space:nowrap;position:sticky;left:0;background:var(--surface);z-index:1;border-right:1.5px solid var(--border);">{{ $p->name }}</td>
                                                    @foreach ($this->kelasList as $k)
                                                        @php
                                                            // Kelas di luar jangkauan pangkat ini (level < startKelas atau >
                                                            // maxKelas) di-disable — kombinasi itu memang tidak pernah kejadian
                                                            // di dunia nyata (pangkat cuma bisa naik kelas dalam rentangnya
                                                            // sendiri). Kalau start/max belum diisi (NULL), tidak ada yang
                                                            // di-disable.
                                                            $startLevel = $p->startKelas?->level;
                                                            $maxLevel = $p->maxKelas?->level;
                                                            $outOfRange = $k->level !== null && (
                                                                ($startLevel !== null && $k->level < $startLevel)
                                                                || ($maxLevel !== null && $k->level > $maxLevel)
                                                            );
                                                        @endphp
                                                        <td style="padding:4px 6px;">
                                                            <input type="number" step="0.01" class="form-input" style="width:120px;font-size:11.5px;padding:6px 8px;{{ $outOfRange ? 'opacity:0.4;' : 'border-color:#042c6c;' }}{{ $errors->has("jabatanMatrix.{$p->id}|{$k->id}") ? 'border-color:#dc2626;' : '' }}" wire:model="jabatanMatrix.{{ $p->id }}|{{ $k->id }}" @disabled($outOfRange) @if ($outOfRange) title="Kelas {{ $k->name }} di luar jangkauan pangkat {{ $p->name }} ({{ $startLevel ?? '?' }}–{{ $maxLevel ?? '?' }})" @endif>
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if ($errors->has('jabatanMatrix.*'))
                                    <p style="font-size:11px;color:#dc2626;margin-top:8px;">Ada nominal yang tidak valid (kotak bergaris merah) — perbaiki dulu sebelum simpan.</p>
                                @endif
                                <button type="button" wire:click="saveJabatan" wire:loading.attr="disabled" wire:target="saveJabatan" class="btn-primary" style="margin-top:14px;padding:9px 20px;font-size:12.5px;">Simpan</button>
                            @endif
                        @endif

                        {{-- Tunjangan Masa Kerja (bracket tahun kerja) --}}
                        @if ($activeComponent === 'tunjangan_masa_kerja')
                            <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Jenjang tahun kerja -> nominal tunjangan (per perusahaan, bukan per pangkat/kelas). Kosongkan "Sampai Tahun" untuk jenjang teratas (tanpa batas atas).</p>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                @foreach ($tenureRows as $i => $row)
                                    <div>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <input type="number" class="form-input" style="max-width:120px;font-size:12.5px;padding:7px 10px;" wire:model="tenureRows.{{ $i }}.min_years" placeholder="Dari tahun">
                                            <span style="font-size:11px;color:var(--text-faint);">s/d</span>
                                            <input type="number" class="form-input" style="max-width:120px;font-size:12.5px;padding:7px 10px;" wire:model="tenureRows.{{ $i }}.max_years" placeholder="Tanpa batas">
                                            <input type="number" step="0.01" class="form-input" style="max-width:200px;font-size:12.5px;padding:7px 10px;" wire:model="tenureRows.{{ $i }}.amount" placeholder="Nominal (Rp)">
                                            <button type="button" wire:click="removeTenureRow({{ $i }})" style="border:none;background:none;color:#dc2626;cursor:pointer;padding:6px;"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                        @error("tenureRows.{$i}.min_years") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                        @error("tenureRows.{$i}.max_years") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                        @error("tenureRows.{$i}.amount") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                                @if (empty($tenureRows))
                                    <p style="font-size:11.5px;color:var(--text-faint);">Belum ada jenjang. Klik "Tambah Jenjang" di bawah.</p>
                                @endif
                            </div>
                            <div style="display:flex;gap:10px;margin-top:14px;">
                                <button type="button" wire:click="addTenureRow" class="btn-ghost" style="padding:9px 16px;font-size:12.5px;"><i class="fa-solid fa-plus"></i> Tambah Jenjang</button>
                                <button type="button" wire:click="saveTenure" wire:loading.attr="disabled" wire:target="saveTenure" class="btn-primary" style="padding:9px 20px;font-size:12.5px;">Simpan</button>
                            </div>
                        @endif

                        {{-- Lembur — base rate per pangkat, dipakai rumus TL/TM: base rate ÷ (hari
                             dalam bulan × 7) × multiplier jam (2 jam → 3,5x, 3 jam → 5,5x, ≥4 jam
                             → 7,5x, mentok di 4 jam). Satu-satunya metode Lembur di sistem ini. --}}
                        @if ($activeComponent === 'lembur')
                            <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Base rate lembur per pangkat. Dihitung per kejadian lembur: base rate ÷ (jumlah hari dalam bulan × 7) × multiplier jam. Pangkat yang belum diisi tidak akan menghasilkan Lembur.</p>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                @foreach ($this->pangkats as $p)
                                    <div>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <span style="flex:1;font-size:12.5px;font-weight:600;">{{ $p->name }}</span>
                                            <input type="number" step="0.01" class="form-input" style="max-width:220px;font-size:12.5px;padding:7px 10px;" wire:model="lemburRateValues.{{ $p->id }}" placeholder="Base rate (Rp)">
                                        </div>
                                        @error("lemburRateValues.{$p->id}") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;text-align:right;">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                                @if ($this->pangkats->isEmpty())
                                    <p style="font-size:11.5px;color:var(--text-faint);">Belum ada data Pangkat untuk perusahaan ini.</p>
                                @endif
                            </div>
                            <button type="button" wire:click="saveLemburRates" wire:loading.attr="disabled" wire:target="saveLemburRates" class="btn-primary" style="margin-top:14px;padding:9px 20px;font-size:12.5px;">Simpan</button>
                        @endif

                        {{-- Kelebihan Jam Kerja — reuse Filament table+action --}}
                        @if ($activeComponent === 'kelebihan_jam_kerja')
                            {{ $this->table }}
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
