@php
    $th = 'text-align:left;padding:6px 10px;white-space:nowrap;';
    $td = 'padding:6px 10px;';
    $box = 'max-height:280px;overflow:auto;border:1px solid rgba(128,128,128,.3);border-radius:8px;';
    $tr = 'border-bottom:1px solid rgba(128,128,128,.2);';
    $profileLabelNames = [
        'email_pribadi' => 'Email Pribadi',
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

<x-filament-panels::page>
    <x-filament::section>
        <p style="font-size:13px;">
            Kolom nip/nama wajib diisi. Kolom lain (email, status_karyawan, tanggal_mulai_kerja, jenis_kelamin, status_perkawinan, tempat_lahir, tanggal_lahir, agama, alamat, nik) opsional — kalau kosong di file, data existing TIDAK ditimpa. Khusus akun baru: email wajib (buat akun) dan tanggal_lahir wajib (buat password awal).
            NIP dipakai sebagai kunci pencocokan: NIP baru → akun baru, NIP sudah ada tapi ada field beda → diperbarui. Belum ada yang tersimpan sampai klik "Konfirmasi Import" di pratinjau.
        </p>
    </x-filament::section>

    <x-filament::section>
        <div style="display:flex;flex-direction:column;gap:16px;">
            @if (auth()->user()->canFilterByCompany())
                <x-filament::input.wrapper style="max-width:360px;">
                    <x-filament::input.select wire:model.live="companyId">
                        @foreach ($this->companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            @endif

            <div>
                <label style="font-size:13px;font-weight:600;">File Excel (.xlsx)</label>
                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" style="display:block;margin-top:6px;font-size:13px;">
                @error('file') <p style="font-size:12px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                @error('companyId') <p style="font-size:12px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                <div wire:loading wire:target="file,companyId" style="font-size:12px;opacity:.7;margin-top:4px;">Memproses pratinjau...</div>
            </div>
        </div>
    </x-filament::section>

    @if ($importErrors !== [])
        <x-filament::section>
            <p style="margin:0 0 4px;font-weight:600;color:#dc2626;">{{ count($importErrors) }} baris di file tidak bisa diproses:</p>
            <ul style="margin:0;padding-left:18px;color:#dc2626;font-size:13px;list-style:disc;">
                @foreach ($importErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($this->newRows !== [])
        <x-filament::section :heading="'Akun Baru (' . count($this->newRows) . ')'" icon="heroicon-o-plus-circle" icon-color="success">
            <div style="{{ $box }}">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="{{ $tr }}">
                            @foreach (['NIP', 'Nama', 'Email', 'Email Pribadi', 'Status Karyawan', 'Mulai Kerja', 'JK', 'Status Kawin', 'Tempat Lahir', 'Tgl Lahir', 'Agama', 'Alamat', 'NIK', 'Password Awal'] as $h)
                                <th style="{{ $th }}">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->newRows as $row)
                            <tr style="{{ $tr }}">
                                <td style="{{ $td }}">{{ $row['nip'] }}</td>
                                <td style="{{ $td }}">{{ $row['nama'] }}</td>
                                <td style="{{ $td }}">{{ $row['email'] }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['email_pribadi'] ?? '-' }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['employee_type_id'] ?? '-' }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['joining_date'] ?? '-' }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['gender'] ?? '-' }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['marital_status'] ?? '-' }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['place_of_birth'] ?? '-' }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['date_of_birth'] ?? '-' }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['religion_id'] ?? '-' }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['address'] ?? '-' }}</td>
                                <td style="{{ $td }}">{{ $row['profile']['no_nik'] ?? '-' }}</td>
                                <td style="{{ $td }}font-family:monospace;">{{ $row['password'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($this->changedRows !== [])
        <x-filament::section :heading="'Ada Perubahan (' . count($this->changedRows) . ')'" icon="heroicon-o-pencil-square" icon-color="warning">
            <div style="{{ $box }}">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="{{ $tr }}">
                            <th style="{{ $th }}">NIP</th>
                            <th style="{{ $th }}">Nama (Lama → Baru)</th>
                            <th style="{{ $th }}">Email (Lama → Baru)</th>
                            <th style="{{ $th }}">Field Profil Berubah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->changedRows as $row)
                            <tr style="{{ $tr }}">
                                <td style="{{ $td }}">{{ $row['nip'] }}</td>
                                <td style="{{ $td }}">
                                    @if ($row['nama_lama'] !== $row['nama_baru'])
                                        <span style="opacity:.6;text-decoration:line-through;">{{ $row['nama_lama'] }}</span>
                                        → <strong>{{ $row['nama_baru'] }}</strong>
                                    @else
                                        {{ $row['nama_baru'] }}
                                    @endif
                                </td>
                                <td style="{{ $td }}">
                                    @if ($row['email_lama'] !== $row['email_baru'])
                                        <span style="opacity:.6;text-decoration:line-through;">{{ $row['email_lama'] }}</span>
                                        → <strong>{{ $row['email_baru'] }}</strong>
                                    @else
                                        {{ $row['email_baru'] }}
                                    @endif
                                </td>
                                <td style="{{ $td }}">
                                    @foreach (($row['profile_baru'] ?? []) as $field => $newValue)
                                        <div>
                                            <strong>{{ $profileLabelNames[$field] ?? $field }}:</strong>
                                            <span style="opacity:.6;text-decoration:line-through;">{{ $row['profile_lama'][$field] ?? '-' }}</span>
                                            → <strong>{{ $newValue }}</strong>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Kategori 3: Tidak Ada di File - murni informasional, tidak ada aksi --}}
    @if ($missingRows !== [])
        <x-filament::section :heading="'Ada di Sistem, Tidak Disebut di File (' . count($missingRows) . ')'" description="Cuma informasi — tidak ada perubahan otomatis untuk akun di bawah ini." icon="heroicon-o-exclamation-triangle" collapsed>
            <div style="{{ $box }}">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="{{ $tr }}">
                            <th style="{{ $th }}">NIP</th>
                            <th style="{{ $th }}">Nama</th>
                            <th style="{{ $th }}">Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($missingRows as $row)
                            <tr style="{{ $tr }}">
                                <td style="{{ $td }}">{{ $row['nip'] }}</td>
                                <td style="{{ $td }}">{{ $row['name'] }}</td>
                                <td style="{{ $td }}">{{ $row['email'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($this->unchangedCount > 0)
        <p style="font-size:12px;opacity:.7;">{{ $this->unchangedCount }} baris lain sudah sama persis dengan sistem — dilewati.</p>
    @endif

    @if ($this->newRows !== [] || $this->changedRows !== [])
        <div style="display:flex;gap:8px;">
            <x-filament::button wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport" icon="heroicon-o-check">
                Konfirmasi Import ({{ count($this->newRows) + count($this->changedRows) }} baris)
            </x-filament::button>
            <x-filament::button wire:click="cancelPreview" color="gray">Batal</x-filament::button>
        </div>
    @endif

    @if ($successCount !== null)
        <x-filament::section>
            <p style="margin:0;font-weight:600;">{{ $successCount }} akun berhasil dibuat/diperbarui.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
