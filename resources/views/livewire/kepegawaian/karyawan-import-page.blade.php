<div>
    <x-page-header title="Import Akun Karyawan" subtitle="Bikin/perbarui akun dasar + profil ringkas (nama, email, NIP, status karyawan, tanggal mulai kerja, tanggal lahir, tempat lahir, alamat) lewat file Excel" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;max-width:900px;">
        <div style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:10px;padding:14px 16px;font-size:12.5px;color:var(--text-body);">
            <i class="fa-solid fa-circle-info"></i>
            Kolom nip/nama wajib diisi. Kolom lain (email, status_karyawan, tanggal_mulai_kerja, jenis_kelamin, status_perkawinan, tempat_lahir, tanggal_lahir, agama, alamat, nik) opsional — kalau kosong di file, data existing TIDAK ditimpa, tetap ikut yang sudah ada di sistem. Khusus akun baru: email wajib (buat akun) dan tanggal_lahir wajib (buat password awal).
            NIP dipakai sebagai kunci pencocokan: NIP baru → akun baru, NIP sudah ada tapi ada field beda → diperbarui. Belum ada apa-apa yang tersimpan sampai kamu klik "Konfirmasi Import" di pratinjau.
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
            <x-company-filter :companies="$this->companies" wire:model.live="companyId" />

            <a href="{{ route('kepegawaian.karyawan.import.template') }}" class="btn-ghost" style="padding:9px 18px;font-size:12.5px;text-decoration:none;">
                <i class="fa-solid fa-download"></i> Unduh Template
            </a>
        </div>

        <div>
            <label class="form-label">File Excel (.xlsx)</label>
            <input type="file" class="form-input" wire:model="file" accept=".xlsx,.xls,.csv">
            <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kolom wajib: nip, nama (+ email &amp; tanggal_lahir khusus akun baru). Opsional: status_karyawan, tanggal_mulai_kerja, jenis_kelamin, status_perkawinan, tempat_lahir, agama, alamat, nik.</p>
            @error('file') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
            @error('companyId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
            <div wire:loading wire:target="file,companyId" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Memproses pratinjau...</div>
        </div>

        @if ($importErrors !== [])
            <div style="background:var(--danger-soft-bg);border:1px solid var(--danger-soft-border);border-radius:10px;padding:12px 14px;font-size:12.5px;">
                <p style="margin:0 0 4px;font-weight:600;color:var(--danger-soft-text);">{{ count($importErrors) }} baris di file tidak bisa diproses:</p>
                <ul style="margin:0;padding-left:18px;color:var(--danger-soft-text);">
                    @foreach ($importErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($this->newRows !== [] || $this->changedRows !== [] || $missingRows !== [])
            <div style="display:flex;flex-direction:column;gap:20px;">

                {{-- Kategori 1: Baru --}}
                @if ($this->newRows !== [])
                    <div>
                        <p style="font-size:13px;font-weight:700;color:var(--brand-success);margin:0 0 8px;">
                            <i class="fa-solid fa-circle-plus"></i> Akun Baru ({{ count($this->newRows) }})
                        </p>
                        <div style="max-height:280px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;">
                            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--surface);">
                                        <th style="text-align:left;padding:6px 10px;">NIP</th>
                                        <th style="text-align:left;padding:6px 10px;">Nama</th>
                                        <th style="text-align:left;padding:6px 10px;">Email</th>
                                        <th style="text-align:left;padding:6px 10px;">Status Karyawan</th>
                                        <th style="text-align:left;padding:6px 10px;">Mulai Kerja</th>
                                        <th style="text-align:left;padding:6px 10px;">JK</th>
                                        <th style="text-align:left;padding:6px 10px;">Status Kawin</th>
                                        <th style="text-align:left;padding:6px 10px;">Tempat Lahir</th>
                                        <th style="text-align:left;padding:6px 10px;">Tgl Lahir</th>
                                        <th style="text-align:left;padding:6px 10px;">Agama</th>
                                        <th style="text-align:left;padding:6px 10px;">Alamat</th>
                                        <th style="text-align:left;padding:6px 10px;">NIK</th>
                                        <th style="text-align:left;padding:6px 10px;">Password Awal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->newRows as $row)
                                        <tr style="border-bottom:1px solid var(--border-soft);">
                                            <td style="padding:6px 10px;">{{ $row['nip'] }}</td>
                                            <td style="padding:6px 10px;">{{ $row['nama'] }}</td>
                                            <td style="padding:6px 10px;">{{ $row['email'] }}</td>
                                            <td style="padding:6px 10px;">{{ $row['profile']['employee_type_id'] ?? '-' }}</td>
                                            <td style="padding:6px 10px;">{{ $row['profile']['joining_date'] ?? '-' }}</td>
                                            <td style="padding:6px 10px;">{{ $row['profile']['gender'] ?? '-' }}</td>
                                            <td style="padding:6px 10px;">{{ $row['profile']['marital_status'] ?? '-' }}</td>
                                            <td style="padding:6px 10px;">{{ $row['profile']['place_of_birth'] ?? '-' }}</td>
                                            <td style="padding:6px 10px;">{{ $row['profile']['date_of_birth'] ?? '-' }}</td>
                                            <td style="padding:6px 10px;">{{ $row['profile']['religion_id'] ?? '-' }}</td>
                                            <td style="padding:6px 10px;">{{ $row['profile']['address'] ?? '-' }}</td>
                                            <td style="padding:6px 10px;">{{ $row['profile']['no_nik'] ?? '-' }}</td>
                                            <td style="padding:6px 10px;font-family:monospace;">{{ $row['password'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Kategori 2: Berubah --}}
                @if ($this->changedRows !== [])
                    <div>
                        <p style="font-size:13px;font-weight:700;color:#d97706;margin:0 0 8px;">
                            <i class="fa-solid fa-pen"></i> Ada Perubahan ({{ count($this->changedRows) }})
                        </p>
                        <div style="max-height:280px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;">
                            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--surface);">
                                        <th style="text-align:left;padding:6px 10px;">NIP</th>
                                        <th style="text-align:left;padding:6px 10px;">Nama (Lama → Baru)</th>
                                        <th style="text-align:left;padding:6px 10px;">Email (Lama → Baru)</th>
                                        <th style="text-align:left;padding:6px 10px;">Field Profil Berubah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $profileLabelNames = [
                                            'employee_type_id' => 'Status Karyawan',
                                            'joining_date' => 'Mulai Kerja',
                                            'gender' => 'Jenis Kelamin',
                                            'marital_status' => 'Status Perkawinan',
                                            'date_of_birth' => 'Tgl Lahir',
                                            'place_of_birth' => 'Tempat Lahir',
                                            'religion_id' => 'Agama',
                                            'address' => 'Alamat',
                                            'no_nik' => 'NIK',
                                        ];
                                    @endphp
                                    @foreach ($this->changedRows as $row)
                                        <tr style="border-bottom:1px solid var(--border-soft);">
                                            <td style="padding:6px 10px;">{{ $row['nip'] }}</td>
                                            <td style="padding:6px 10px;">
                                                @if ($row['nama_lama'] !== $row['nama_baru'])
                                                    <span style="color:var(--text-faint);text-decoration:line-through;">{{ $row['nama_lama'] }}</span>
                                                    → <strong>{{ $row['nama_baru'] }}</strong>
                                                @else
                                                    {{ $row['nama_baru'] }}
                                                @endif
                                            </td>
                                            <td style="padding:6px 10px;">
                                                @if ($row['email_lama'] !== $row['email_baru'])
                                                    <span style="color:var(--text-faint);text-decoration:line-through;">{{ $row['email_lama'] }}</span>
                                                    → <strong>{{ $row['email_baru'] }}</strong>
                                                @else
                                                    {{ $row['email_baru'] }}
                                                @endif
                                            </td>
                                            <td style="padding:6px 10px;">
                                                @foreach (($row['profile_baru'] ?? []) as $field => $newValue)
                                                    <div>
                                                        <strong>{{ $profileLabelNames[$field] ?? $field }}:</strong>
                                                        <span style="color:var(--text-faint);text-decoration:line-through;">{{ $row['profile_lama'][$field] ?? '-' }}</span>
                                                        → <strong>{{ $newValue }}</strong>
                                                    </div>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Kategori 3: Tidak Ada di File - murni informasional, tidak ada aksi --}}
                @if ($missingRows !== [])
                    <div>
                        <p style="font-size:13px;font-weight:700;color:var(--text-muted);margin:0 0 8px;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Ada di Sistem, Tidak Disebut di File ({{ count($missingRows) }})
                        </p>
                        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 8px;">Cuma informasi — tidak ada perubahan otomatis untuk akun di bawah ini.</p>
                        <div style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;">
                            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--surface);">
                                        <th style="text-align:left;padding:6px 10px;">NIP</th>
                                        <th style="text-align:left;padding:6px 10px;">Nama</th>
                                        <th style="text-align:left;padding:6px 10px;">Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($missingRows as $row)
                                        <tr style="border-bottom:1px solid var(--border-soft);">
                                            <td style="padding:6px 10px;">{{ $row['nip'] }}</td>
                                            <td style="padding:6px 10px;">{{ $row['name'] }}</td>
                                            <td style="padding:6px 10px;">{{ $row['email'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($this->unchangedCount > 0)
                    <p style="font-size:11.5px;color:var(--text-faint);margin:0;">{{ $this->unchangedCount }} baris lain sudah sama persis dengan sistem — dilewati.</p>
                @endif

                @if ($this->newRows !== [] || $this->changedRows !== [])
                    <div style="display:flex;gap:8px;">
                        <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport" class="btn-primary" style="padding:10px 18px;font-size:13px;">
                            <span wire:loading.remove wire:target="confirmImport"><i class="fa-solid fa-floppy-disk"></i> Konfirmasi Import ({{ count($this->newRows) + count($this->changedRows) }} baris)</span>
                            <span wire:loading wire:target="confirmImport"><i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...</span>
                        </button>
                        <button type="button" wire:click="cancelPreview" class="btn-ghost" style="padding:10px 18px;font-size:13px;">Batal</button>
                    </div>
                @endif
            </div>
        @endif

        @if ($successCount !== null)
            <div style="background:{{ $importErrors === [] ? 'var(--info-soft-bg)' : 'var(--warn-soft-bg)' }};border-radius:10px;padding:12px 14px;font-size:12.5px;">
                <p style="margin:0;font-weight:600;">{{ $successCount }} akun berhasil dibuat/diperbarui.</p>
            </div>
        @endif
    </div>
</div>
