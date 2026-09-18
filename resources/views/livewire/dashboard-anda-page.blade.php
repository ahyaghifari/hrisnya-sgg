<div>
    <x-page-header title="Dashboard Anda" subtitle="Monitoring & ringkasan sesuai jabatan Anda" />

    @if ($this->canFilterByCompany)
        <div class="mb-4" style="max-width:280px;">
            <label class="form-label">Perusahaan</label>
            <select wire:model.live="companyId" class="form-input">
                @foreach ($this->companyOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="bento">
        @if ($this->canDashboardHcSummary)
            <div style="grid-column:1/-1;">
                <livewire:dashboard.summary-strip-widget :company-id="$companyId" :key="'summary-strip-'.$companyId" />
            </div>
        @endif

        @if ($this->canDashboardHcEmployeeType || $this->canDashboardHcAttendanceToday || $this->canDashboardHcAttendanceTrend)
            <div class="grid grid-cols-1 lg:grid-cols-2" style="gap:16px;grid-column:1/-1;">
                @if ($this->canDashboardHcEmployeeType)
                    <livewire:dashboard.employee-type-breakdown-widget :company-id="$companyId" :key="'employee-type-breakdown-'.$companyId" />
                @endif
                @if ($this->canDashboardHcAttendanceToday || $this->canDashboardHcAttendanceTrend)
                    <div style="display:flex;flex-direction:column;gap:16px;">
                        @if ($this->canDashboardHcAttendanceToday)
                            <livewire:dashboard.today-attendance-summary-widget :company-id="$companyId" :key="'today-attendance-summary-'.$companyId" />
                        @endif
                        @if ($this->canDashboardHcAttendanceTrend)
                            <livewire:dashboard.attendance-trend-widget :company-id="$companyId" :key="'attendance-trend-'.$companyId" />
                        @endif
                    </div>
                @endif
            </div>
        @endif

        @if ($this->canDashboardHcCoaching)
            <livewire:dashboard.coaching-widget :company-id="$companyId" :key="'coaching-widget-'.$companyId" />
        @endif

        @if ($this->canDashboardHcManpower)
            <livewire:dashboard.manpower-gap-widget :company-id="$companyId" :key="'manpower-gap-'.$companyId" />
        @endif
    </div>
</div>
