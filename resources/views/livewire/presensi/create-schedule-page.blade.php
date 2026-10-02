<div class="create-schedule-page">
    {{-- Sembunyikan ikon jam bawaan browser di input time — khusus halaman ini saja, scoped
         lewat class di root, bukan global (tabel jam kerja lebih rapi tanpa ikon dobel). --}}
    <style>
        .create-schedule-page input[type="time"]::-webkit-calendar-picker-indicator {
            display: none;
            -webkit-appearance: none;
        }
        .create-schedule-page input[type="time"] {
            -moz-appearance: textfield;
        }
        /* Nama Jadwal — cuma border bawah yang berubah biru pas fokus, bukan ring/outline
           bawaan browser (yang biasanya nambahin kotak biru di semua sisi). */
        .create-schedule-page .schedule-name-input {
            outline: none;
            box-shadow: none;
            transition: border-color 0.15s;
        }
        .create-schedule-page .schedule-name-input:focus {
            outline: none;
            box-shadow: none;
            border-bottom-color: #042c6c;
        }
        /* Tooltip "Rincian" — hover CSS buat show/hide, posisi (top/left) dihitung JS
           (resources/js/app.js) pakai position:fixed supaya lolos dari overflow-x:auto
           pembungkus tabel (position:absolute biasa ke-clip di situ). Tanpa roundtrip Livewire
           sama sekali (beda dari modal Pengaturan Lanjutan yang memang perlu wire:click). */
        .create-schedule-page .rincian-tooltip-wrap {
            position: relative;
            display: inline-block;
        }
        .create-schedule-page .rincian-tooltip {
            position: fixed;
            top: 0;
            left: 0;
            transform: translate(-100%, -50%);
            width: 340px;
            box-sizing: border-box;
            background: var(--surface);
            color: var(--text-body);
            font-size: 12px;
            line-height: 1.7;
            text-align: left;
            white-space: normal;
            padding: 16px 18px;
            border-radius: 10px;
            border: 1px solid var(--border-soft);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.15s ease, visibility 0.15s ease;
            z-index: 20;
            pointer-events: none;
        }
        .create-schedule-page .rincian-tooltip .rincian-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0 0 10px;
        }
        .create-schedule-page .rincian-tooltip .rincian-line {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            color: var(--text-body);
        }
        .create-schedule-page .rincian-tooltip .rincian-line > span {
            flex: 1;
            min-width: 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .create-schedule-page .rincian-tooltip .rincian-line i {
            color: var(--text-faint);
            font-size: 11px;
            margin-top: 3px;
            width: 12px;
            flex-shrink: 0;
        }
        .create-schedule-page .rincian-tooltip hr {
            border: none;
            border-top: 1px solid var(--border-soft);
            margin: 10px 0;
        }
        .create-schedule-page .rincian-tooltip ul {
            margin: 0;
            padding-left: 16px;
        }
        .create-schedule-page .rincian-tooltip-wrap:hover .rincian-tooltip {
            opacity: 1;
            visibility: visible;
        }
    </style>

    <x-page-header title="{{ $editingScheduleId ? 'Edit Jadwal' : 'Tambah Jadwal' }}" back="{{ $editingScheduleId ? route('presensi.jadwal.show', $editingScheduleId) : route('presensi.jadwal') }}" />

    @php
        $typeMeta = [
            'flexible' => ['icon' => 'jadwal-flexible.png', 'desc' => 'Karyawan boleh datang & pulang kapan pun, yang dihitung total jam kerjanya per hari.'],
            'fixed' => ['icon' => 'jadwal-fixed.png', 'desc' => 'Jam masuk & pulang sama setiap hari kerja, cocok untuk jadwal kantor normal.'],
            'shift' => ['icon' => 'jadwal-shift.png', 'desc' => 'Rotasi kerja (Pagi/Siang/Malam, dst) yang dijadwalkan per periode, biasanya bulanan.'],
        ];
    @endphp

    @if ($step === 1)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:36px 28px;">
            <p style="text-align:center;font-size:14px;color:var(--text-muted);margin:0 0 30px;">
                Pilih jenis jadwal kerja yang ingin diberlakukan, tentukan detailnya di langkah berikutnya.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:28px;">
                @foreach ($this->scheduleTypes as $type)
                    @php $isSelected = $scheduleType === $type->value; @endphp
                    <button type="button" wire:click="selectType('{{ $type->value }}')"
                        style="background:none;border:none;padding:0;cursor:pointer;text-align:center;font-family:inherit;">
                        <div style="width:100%;max-width:260px;aspect-ratio:1;margin:0 auto 18px;border-radius:28px;border:2px solid {{ $isSelected ? '#042c6c' : 'var(--border)' }};background:{{ $isSelected ? 'rgba(4,44,108,0.06)' : 'var(--surface)' }};display:flex;align-items:center;justify-content:center;transition:all 0.15s;">
                            <img src="{{ asset('assets/' . $typeMeta[$type->value]['icon']) }}" alt="{{ $type->getLabel() }}" style="width:75%;height:75%;object-fit:contain;">
                        </div>

                        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:10px;">
                            <span style="width:18px;height:18px;border-radius:50%;border:2px solid {{ $isSelected ? '#042c6c' : 'var(--border)' }};display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                                @if ($isSelected)
                                    <span style="width:9px;height:9px;border-radius:50%;background:#042c6c;"></span>
                                @endif
                            </span>
                            <span style="font-size:15px;font-weight:700;color:var(--text-heading);">{{ $type->getLabel() }}</span>
                        </div>

                        <p style="font-size:12.5px;color:var(--text-muted);margin:0;line-height:1.6;">{{ $typeMeta[$type->value]['desc'] }}</p>
                    </button>
                @endforeach
            </div>

            @error('scheduleType') <p style="font-size:11.5px;color:#dc2626;text-align:center;margin-top:16px;">{{ $message }}</p> @enderror

            <div style="display:flex;justify-content:flex-end;margin-top:26px;border-top:1px solid var(--border-soft);padding-top:18px;">
                <button type="button" wire:click="nextStep" class="btn-primary">
                    Selanjutnya <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>
    @else
        @php
            $zoneWork = 'rgba(4,44,108,0.08)';
            $zoneOvertime = 'transparent';
            $numStyle = 'padding:4px 3px;font-size:10.5px;text-align:center;font-variant-numeric:tabular-nums;';
            $dataColCount = $scheduleType === 'fixed' ? 9 : 6;
        @endphp

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
            {{-- Header: identitas tipe + judul jadwal, bukan form biasa (nama jadwal itu "judul dokumen"-nya) --}}
            <div style="padding:20px 22px;border-bottom:1px solid var(--border-soft);">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:rgba(4,44,108,0.06);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <img src="{{ asset('assets/' . $typeMeta[$scheduleType]['icon']) }}" alt="" style="width:26px;height:26px;object-fit:contain;">
                        </div>
                        <div>
                            <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0;">{{ \App\Enum\ScheduleType::from($scheduleType)->getLabel() }}</p>
                            @unless ($editingScheduleId)
                                <button type="button" wire:click="backStep" style="background:none;border:none;padding:0;font-size:10.5px;color:var(--brand);cursor:pointer;font-family:inherit;">Ganti tipe jadwal</button>
                            @endunless
                        </div>
                    </div>
                    <span style="font-size:10.5px;font-weight:700;letter-spacing:0.03em;color:var(--text-faint);white-space:nowrap;">{{ $editingScheduleId ? 'MODE EDIT' : 'LANGKAH 2/2' }}</span>
                </div>

                <div style="display:grid;grid-template-columns:{{ auth()->user()->canFilterByCompany() ? '2fr 1fr' : '1fr' }};gap:14px;">
                    <div>
                        <label class="form-label">Nama Jadwal</label>
                        <input type="text" wire:model="name" class="schedule-name-input"
                            style="width:100%;border:none;border-bottom:2px solid var(--border);background:none;font-size:19px;font-weight:700;color:var(--text-heading);padding:4px 2px 8px;font-family:inherit;"
                            placeholder="Mis. Jam Kerja Normal">
                        @error('name') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if (auth()->user()->canFilterByCompany())
                        <div>
                            <label class="form-label">Perusahaan</label>
                            <select class="form-input" wire:model.live="companyId" @disabled($editingScheduleId) style="{{ $editingScheduleId ? 'opacity:0.6;cursor:not-allowed;' : '' }}">
                                @foreach ($this->companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                            @if ($editingScheduleId)
                                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Perusahaan tidak bisa diganti setelah jadwal dibuat.</p>
                            @endif
                            @error('companyId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                @if ($scheduleType === 'shift')
                    <div style="margin-top:14px;max-width:320px;">
                        <label class="form-label">Kepala Ruangan</label>
                        <select class="form-input" wire:model="headUserId" data-tom-select data-placeholder="-- Tidak ada --">
                            <option value="">-- Tidak ada --</option>
                            @foreach ($this->headUserOptions as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        <p style="font-size:10.5px;color:var(--text-faint);margin-top:4px;">Dipakai widget "Jadwal Shift Tim" di Dashboard supaya tombol "Detail Jadwal" mengarah ke jadwal yang tepat.</p>
                        @error('headUserId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="margin-top:14px;max-width:320px;" wire:key="editors-{{ $companyId }}">
                        <label class="form-label">Pengisi Jadwal</label>
                        <select class="form-input" wire:model="editorUserIds" multiple data-tom-select data-placeholder="-- Tidak ada --">
                            @foreach ($this->headUserOptions as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        <p style="font-size:10.5px;color:var(--text-faint);margin-top:4px;">Selain Kepala Ruangan, mereka juga bisa mengisi roster jadwal ini (perubahan tetap lewat approval).</p>
                    </div>

                    <label style="display:flex;align-items:flex-start;gap:10px;margin-top:14px;max-width:420px;cursor:pointer;">
                        <span class="custom-checkbox" style="margin-top:1px;">
                            <input type="checkbox" wire:model="allowOncallExtraShift">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </span>
                        <span>
                            <span style="display:block;font-size:12.5px;font-weight:600;color:var(--text-heading);">Oncall boleh jadi Extra Shift</span>
                            <span style="display:block;font-size:10.5px;color:var(--text-faint);margin-top:2px;">Kalau dinyalakan, Oncall ikut muncul sebagai pilihan Extra Shift untuk jadwal ini &mdash; di luar aturan Pagi/Siang/Malam yang diatur admin (Pengaturan &gt; Aturan Extra Shift).</span>
                        </span>
                    </label>
                @endif

                @if ($scheduleType === 'flexible')
                    <div style="display:flex;align-items:center;gap:10px;margin-top:14px;padding:10px 12px;background:var(--surface-alt);border-radius:10px;">
                        <i class="fa-regular fa-clock" style="color:var(--text-faint);font-size:13px;"></i>
                        <span style="font-size:11.5px;color:var(--text-muted);white-space:nowrap;">Jendela absensi:</span>
                        <input type="time" class="form-input" style="padding:6px 10px;font-size:12px;width:auto;" wire:model="flexibleCheckinFrom">
                        <span style="color:var(--text-faint);">&ndash;</span>
                        <input type="time" class="form-input" style="padding:6px 10px;font-size:12px;width:auto;" wire:model="flexibleCheckinUntil">
                        <span style="font-size:10.5px;color:var(--text-faint);">berlaku semua hari kerja</span>
                    </div>
                @endif

                @if ($scheduleType === 'shift')
                    <div style="display:flex;align-items:center;gap:10px;margin-top:14px;padding:10px 12px;background:var(--surface-alt);border-radius:10px;">
                        <i class="fa-solid fa-layer-group" style="color:var(--text-faint);font-size:13px;"></i>
                        <span style="font-size:11.5px;color:var(--text-muted);white-space:nowrap;">Pola Shift:</span>
                        <select class="form-input" style="padding:6px 10px;font-size:12px;width:auto;" wire:model="shiftPattern">
                            <option value="">Pilih pola shift...</option>
                            @foreach ($this->shiftPatterns as $pattern)
                                <option value="{{ $pattern->value }}">{{ $pattern->label() }}</option>
                            @endforeach
                        </select>
                        @error('shiftPattern') <span style="font-size:11px;color:#dc2626;">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>

            {{-- Form-nya dipecah per tipe jadwal — lihat resources/views/livewire/presensi/partials/. --}}
            @if ($scheduleType === 'shift')
                @include('livewire.presensi.partials.schedule-shift-form')
            @else
                @include('livewire.presensi.partials.schedule-day-form')
            @endif

            <div style="display:flex;justify-content:space-between;gap:8px;padding:16px 22px;border-top:1px solid var(--border-soft);background:var(--surface-alt);">
                @if ($editingScheduleId)
                    <a href="{{ route('presensi.jadwal.show', $editingScheduleId) }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left"></i> Batal</a>
                @else
                    <button type="button" wire:click="backStep" class="btn-ghost"><i class="fa-solid fa-arrow-left"></i> Kembali</button>
                @endif
                <button type="button" wire:click="save" class="btn-primary">{{ $editingScheduleId ? 'Simpan Perubahan' : 'Simpan Jadwal' }}</button>
            </div>
        </div>
        {{-- Modal "Pengaturan Lanjutan" tiap tipe ikut pindah ke partial masing-masing
             (position:fixed jadi tetap full-screen overlay walau nested lebih dalam). --}}
    @endif
</div>
