<div style="grid-column:1/-1;">
    <div class="card-surface accent accent-teal">
        <div class="section-head" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
            <div class="section-head-icon">
                <div class="section-icon"><i class="fa-solid fa-sitemap"></i></div>
                <div>
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">
                        Manpower Planning — Gap per {{ $this->gapLevelLabel }}
                    </p>
                    <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">
                        @if ($this->selectedDivisionId)
                            Jabatan di dalam {{ $this->selectedDivisionName }}
                        @elseif ($this->selectedDepartmentId)
                            Divisi di dalam {{ $this->selectedDepartmentName }}
                        @else
                            Karyawan aktif vs kebutuhan (kuota jabatan) per departemen
                        @endif
                    </p>
                </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <select wire:model.live="selectedDepartmentId" class="form-input" style="max-width:200px;">
                    <option value="">Semua Departemen</option>
                    @foreach ($this->departmentOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>

                @if ($this->selectedDepartmentId)
                    <select wire:model.live="selectedDivisionId" class="form-input" style="max-width:200px;">
                        <option value="">Semua Divisi</option>
                        @foreach ($this->divisionOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        <div style="display:flex;align-items:flex-start;gap:8px;background:var(--warn-soft-bg);border:1px solid var(--warn-soft-border);border-radius:10px;padding:9px 12px;margin-bottom:14px;">
            <i class="fa-solid fa-circle-info" style="color:var(--warn-soft-text);font-size:12px;margin-top:1px;"></i>
            <p style="font-size:11px;color:var(--warn-soft-text);margin:0;line-height:1.5;">Data kuota jabatan (kolom "kebutuhan") saat ini masih belum final — angka gap di bawah bisa berubah setelah kuota per jabatan dirapikan.</p>
        </div>

        @if ($this->selectedDepartmentId)
            <button type="button" wire:click="$set('selectedDepartmentId', null)" style="background:none;border:none;color:var(--brand);font-size:11.5px;font-weight:600;cursor:pointer;padding:0;margin-bottom:12px;font-family:inherit;">
                <i class="fa-solid fa-arrow-left" style="font-size:10px;"></i> Kembali ke semua departemen
            </button>
        @endif

        @if ($this->gapRows->isNotEmpty())
            <div style="position:relative;width:100%;height:220px;margin-bottom:16px;">
                <canvas data-chart-config="{{ json_encode($this->chartConfig) }}"></canvas>
            </div>
        @endif

        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;">
            @forelse ($this->gapRows as $row)
                <div style="background:{{ $row['gap'] < 0 ? '#fee2e2' : ($row['gap'] > 0 ? '#dcfce7' : 'var(--surface-alt)') }};border-radius:12px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <div>
                        <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0;">{{ $row['name'] }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $row['actual'] }} dari {{ $row['quota'] }} kebutuhan</p>
                    </div>
                    @if ($row['gap'] < 0)
                        <span class="badge badge-rejected">{{ $row['gap'] }}</span>
                    @elseif ($row['gap'] > 0)
                        <span class="badge badge-approved">+{{ $row['gap'] }}</span>
                    @else
                        <span class="badge badge-cancelled">Pas</span>
                    @endif
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">
                    @if ($this->selectedDivisionId)
                        Belum ada jabatan terisi di divisi ini.
                    @elseif ($this->selectedDepartmentId)
                        Belum ada jabatan dengan divisi terisi di departemen ini.
                    @else
                        Belum ada jabatan dengan departemen terisi.
                    @endif
                </p>
            @endforelse
        </div>

        @if ($this->significantShortageCount > 0)
            <p style="font-size:11px;color:var(--text-muted);margin-top:14px;"><i class="fa-solid fa-triangle-exclamation" style="color:#b91c1c;"></i> {{ $this->significantShortageCount }} {{ $this->gapLevelLabel }} kekurangan tenaga kerja signifikan (&gt;5 orang) — pertimbangkan ajukan Permintaan Tenaga Kerja.</p>
        @endif
    </div>
</div>
