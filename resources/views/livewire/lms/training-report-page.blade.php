<div>
    <x-page-header title="Laporan Pelatihan" subtitle="Matriks kepatuhan pelatihan karyawan vs baseline" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{-- Filter Periode --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;">
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;justify-content:space-between;">
                <div style="min-width:320px;flex:1;">
                    <label class="form-label">Periode Baseline</label>
                    <div wire:ignore>
                        <select wire:model.live="baselineId" data-tom-select data-placeholder="Pilih baseline pelatihan..." class="form-input">
                            <option value="">Pilih baseline pelatihan...</option>
                            @foreach ($this->baselineOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel"
                    @disabled(! $baselineId) class="btn-primary" style="padding:9px 18px;font-size:12.5px;white-space:nowrap;">
                    <span wire:loading.remove wire:target="exportExcel"><i class="fa-solid fa-file-excel"></i> Export Excel</span>
                    <span wire:loading wire:target="exportExcel">Menyiapkan file...</span>
                </button>
            </div>

            @if ($this->baseline)
                @php $b = $this->baseline; @endphp
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border-soft);">
                    <div>
                        <p style="font-size:11px;color:var(--text-faint);margin:0 0 4px;text-transform:uppercase;letter-spacing:.03em;">Perusahaan</p>
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $b->company?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p style="font-size:11px;color:var(--text-faint);margin:0 0 4px;text-transform:uppercase;letter-spacing:.03em;">Periode</p>
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">
                            {{ \Carbon\Carbon::parse($b->start_date)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($b->end_date)->format('d/m/Y') }}
                        </p>
                    </div>
                    <div>
                        <p style="font-size:11px;color:var(--text-faint);margin:0 0 4px;text-transform:uppercase;letter-spacing:.03em;">Minimum Jam</p>
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $b->min_hours }} jam</p>
                    </div>
                    <div>
                        <p style="font-size:11px;color:var(--text-faint);margin:0 0 4px;text-transform:uppercase;letter-spacing:.03em;">Minimum Pelatihan</p>
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $b->min_courses }} pelatihan</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Tabel matriks --}}
        @if (! $this->baseline)
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:32px;text-align:center;color:var(--text-faint);">
                <i class="fa-solid fa-chart-bar" style="font-size:28px;margin-bottom:10px;display:block;"></i>
                Pilih periode baseline di atas untuk menampilkan laporan.
            </div>
        @else
            @php $matrix = $this->reportMatrix; $courses = $this->baselineCourses; @endphp
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Karyawan</th>
                            @foreach ($courses as $course)
                                <th style="text-align:center;padding:10px 14px;white-space:nowrap;min-width:120px;">{{ $course->title }}</th>
                            @endforeach
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Total Jam Pelatihan</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jam Belum Terpenuhi</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Total Pelatihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matrix as $row)
                            <tr style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $row['nip'] ?: '-' }}</td>
                                <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $row['name'] }}</td>
                                @foreach ($courses as $course)
                                    @php $c = $row['courses'][$course->id]; @endphp
                                    <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                        @if ($c['enrolled'])
                                            <span class="badge" style="background:rgba(4,124,84,0.12);color:var(--brand-success);"><i class="fa-solid fa-circle-check"></i></span>
                                            @if ($c['enrolled_at'])
                                                <div style="font-size:10.5px;color:var(--text-faint);margin-top:2px;">{{ \Carbon\Carbon::parse($c['enrolled_at'])->format('d/m/Y') }}</div>
                                            @endif
                                        @else
                                            <span class="badge" style="background:rgba(220,38,38,0.1);color:#dc2626;"><i class="fa-solid fa-circle-xmark"></i></span>
                                        @endif
                                    </td>
                                @endforeach
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <span class="badge" style="background:{{ $row['total_hours'] >= $b->min_hours ? 'rgba(4,124,84,0.12)' : 'rgba(234,179,8,0.15)' }};color:{{ $row['total_hours'] >= $b->min_hours ? 'var(--brand-success)' : '#a16207' }};">
                                        {{ number_format($row['total_hours'], 0) }} jam
                                    </span>
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <span class="badge" style="background:{{ $row['unfulfilled_hours'] > 0 ? 'rgba(220,38,38,0.1)' : 'rgba(4,124,84,0.12)' }};color:{{ $row['unfulfilled_hours'] > 0 ? '#dc2626' : 'var(--brand-success)' }};">
                                        {{ $row['unfulfilled_hours'] > 0 ? number_format($row['unfulfilled_hours'], 0) . ' jam' : 'Terpenuhi' }}
                                    </span>
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ $row['total_courses'] }} pelatihan</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 5 + $courses->count() }}" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada data karyawan untuk perusahaan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
