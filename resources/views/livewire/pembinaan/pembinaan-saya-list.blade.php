@php
    // Jenis: [label, kelas tile ikon (pwa-tile-icon), ikon, latar badge, warna teks badge]. Badge hard-coded terang
    // seperti badge level SP; tile ikon pakai kelas yang sudah ada di app.css (ada varian dark mode).
    $types = [
        'coaching' => ['Coaching', 'teal', 'fa-seedling', '#ccfbf1', '#0f766e'],
        'counseling' => ['Counseling', 'neutral', 'fa-comments', '#ede9fe', '#6d28d9'],
        'sp' => ['Surat Peringatan', 'danger', 'fa-triangle-exclamation', '#ffe4e6', '#be123c'],
    ];
    $pill = fn (bool $on) => 'padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;'
        . 'background:' . ($on ? 'var(--surface)' : 'transparent') . ';color:' . ($on ? 'var(--text-heading)' : 'var(--text-muted)') . ';box-shadow:' . ($on ? 'var(--shadow-card)' : 'none') . ';';
@endphp
<div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
        <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;flex-wrap:wrap;">
            <button type="button" wire:click="setType('')" style="{{ $pill($type === '') }}">Semua</button>
            @foreach ($types as $key => $t)
                <button type="button" wire:click="setType('{{ $key }}')" style="{{ $pill($type === $key) }}">{{ $t[0] }}</button>
            @endforeach
        </div>
        <span style="display:flex;gap:10px;flex-wrap:wrap;">
            @if ($this->showInboxButton)
                <a href="{{ route('counseling.kelola') }}" wire:navigate class="btn-ghost">
                    <i class="fa-solid fa-inbox"></i> Konsul ke Saya
                </a>
            @endif
            <button type="button" wire:click="openConsultModal" class="btn-primary">
                <i class="fa-solid fa-comment-medical"></i> Konsul
            </button>
        </span>
    </div>

    @if ($rows->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada data pembinaan.</p>
        </div>
    @else
        <!-- MOBILE: card -->
        <div class="md:hidden">
            <div class="pwa-list">
                @foreach ($rows as $row)
                    @php $t = $types[$row['type']]; @endphp
                    <a href="{{ $row['url'] }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                        <div class="pwa-tile-icon {{ $t[1] }}"><i class="fa-solid {{ $t[2] }}"></i></div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $t[0] }} · {{ $row['title'] }}</p>
                            @if ($row['info'])
                                <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $row['info'] }}</p>
                            @endif
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $row['date']->translatedFormat('j F Y') }}</p>
                            <span class="badge {{ $row['badge'] }}" style="margin-top:4px;">{{ $row['status'] }}</span>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- DESKTOP: tabel -->
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tanggal</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Jenis</th>
                            <th style="text-align:left;padding:10px 14px;">Keterangan</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $t = $types[$row['type']]; @endphp
                            <tr wire:key="pembinaan-{{ $row['type'] }}-{{ md5($row['url']) }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $row['date']->translatedFormat('j F Y') }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">
                                    <span class="badge" style="background:{{ $t[3] }};color:{{ $t[4] }};">{{ $t[0] }}</span>
                                </td>
                                <td style="padding:9px 14px;">
                                    {{ $row['title'] }}
                                    @if ($row['info'])
                                        <span style="display:block;font-size:11px;color:var(--text-muted);">{{ $row['info'] }}</span>
                                    @endif
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <span class="badge {{ $row['badge'] }}">{{ $row['status'] }}</span>
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <a href="{{ $row['url'] }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Detail"><i class="fa-solid fa-arrow-right"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($showConsultModal)
        <div class="modal-overlay" wire:click="closeConsultModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Konsul</h3>
                <form wire:submit="submitConsult" style="display:flex;flex-direction:column;gap:14px;">
                    <div>
                        <label class="form-label">Konselor *</label>
                        <select data-tom-select data-placeholder="-- Pilih Konselor --" class="form-input" wire:model="counselorId">
                            <option value="">-- Pilih Konselor --</option>
                            @foreach ($this->counselorOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('counselorId') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Keluhan / Yang ingin dikonsultasikan *</label>
                        <textarea class="form-input" rows="5" wire:model="issue" placeholder="Ceritakan keluhan atau hal yang ingin Anda konsultasikan..."></textarea>
                        @error('issue') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <span class="custom-checkbox">
                                <input type="checkbox" wire:model="isSecret">
                                <span class="box"><i class="fa-solid fa-check"></i></span>
                            </span>
                            <span style="font-size:13px;">Rahasia (cuma saya &amp; konselor yang bisa baca isinya)</span>
                        </label>
                        <p style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">Kalau tidak dicentang, isi konsul ini bisa dibaca siapa pun yang punya akses ke sistem Coaching &amp; Counseling.</p>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;">
                        <button type="button" wire:click="closeConsultModal" class="btn-ghost">Batal</button>
                        <button type="submit" class="btn-primary">Kirim</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
