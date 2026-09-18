<div class="card-surface accent accent-green">
    <div class="section-head" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div class="section-head-icon">
            <div class="section-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div>
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Tren Kehadiran 30 Hari</p>
                <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">
                    @if ($this->selectedDivisionId)
                        {{ $this->selectedDivisionName }}
                    @elseif ($this->selectedDepartmentId)
                        {{ $this->selectedDepartmentName }}
                    @else
                        Seluruh company
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

    @if ($this->selectedDepartmentId)
        <button type="button" wire:click="$set('selectedDepartmentId', null)" style="background:none;border:none;color:var(--brand);font-size:11.5px;font-weight:600;cursor:pointer;padding:0;margin-bottom:12px;font-family:inherit;">
            <i class="fa-solid fa-arrow-left" style="font-size:10px;"></i> Kembali ke semua departemen
        </button>
    @endif

    <div style="position:relative;width:100%;height:220px;">
        <canvas data-chart-config="{{ json_encode($this->chartConfig) }}"></canvas>
    </div>
</div>
