<?php

namespace App\Livewire;

use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\EmployeeTypeCategory;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Enum\UserProfileDocumentType;
use App\Livewire\Concerns\HandlesProfilePhotoUpload;
use App\Livewire\Concerns\ResolvesTodayAttendanceStatus;
use App\Livewire\Concerns\ResyncsOwnAttendance;
use App\Models\AuditLog;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Employee\EmployeeNakesDocument;
use App\Models\Master\Religions;
use App\Models\Employee\Employee;
use App\Models\Payroll\Payroll;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfileDocument;
use App\Models\UserHrBalance;
use App\Services\Attendance\AttendaceReqService;
use App\Services\Attendance\AttendancePeriodResolver;
use App\Services\OrgHierarchyService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class EmployeeStatistics extends Component
{
    use ResolvesTodayAttendanceStatus;
    use ResyncsOwnAttendance;
    use HandlesProfilePhotoUpload;
    use WithFileUploads;
    use WithPagination;

    public User $record;

    public ?string $date_from = null;
    public ?string $date_to = null;

    public string $calendarMonth;

    // ── Edit Data Diri (self-service, lihat saveProfil()) ──
    public bool $editingProfil = false;
    public ?string $nickname = null;
    public ?string $gender = null;
    public ?string $marital_status = null;
    public ?string $religion_id = null;
    public ?string $place_of_birth = null;
    public ?string $date_of_birth = null;
    public ?string $no_nik = null;
    public ?string $no_kk = null;
    public ?string $no_bpjs_kes = null;
    public ?string $no_bpjs_ket = null;
    public ?string $npwp = null;
    public ?string $phone = null;
    public ?string $whatsapp = null;
    public ?string $email_pribadi = null;
    public ?string $country = null;
    public ?string $province = null;
    public ?string $city = null;
    public ?string $district = null;
    public ?string $village = null;
    public ?string $postal_code = null;
    public ?string $rt = null;
    public ?string $rw = null;
    public ?string $address = null;

    // ── Lampiran, satu slot upload per tipe tetap (App\Enum\UserProfileDocumentType) - pilih
    // file langsung upload (pola sama dengan HandlesProfilePhotoUpload::$photo), tidak ada
    // tombol submit terpisah lagi.
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $ktpFile = null;
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $kkFile = null;
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $strFile = null;
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $sipFile = null;
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $rkkFile = null;

    /** Form STR/SIP nakes: [type => [nomor, berlaku_sampai (Y-m-d), seumur_hidup]]. File di $strFile/$sipFile. */
    public array $nakesForm = [
        'STR' => ['nomor' => '', 'berlaku_mulai' => '', 'berlaku_sampai' => '', 'seumur_hidup' => false],
        'SIP' => ['nomor' => '', 'berlaku_mulai' => '', 'berlaku_sampai' => '', 'seumur_hidup' => false],
    ];

    public function mount(?User $record = null): void
    {
        // Route /profil-saya tidak punya segment {record} — otomatis pakai user yang login.
        $record ??= Auth::user();

        abort_unless($this->canManage($record), 403);

        $this->record = $record->load([
            'company',
            'roles',
            'profile.religion',
            'profile.jobPosition.orgStructure.parent.parent',
            'profile.jobPosition',
            'profile.pangkat',
            'profile.kelas',
            'profile.employeeType',
            'activeJobPosition.jobPosition',
        ]);
        // Default periode ikut cutoff absensi company (AttendancePeriodResolver, sama dengan
        // yang dipakai enforcement kuota Cuti/Izin) — bukan kalender 1-31 tetap. User tetap
        // bisa timpa manual lewat filter Dari/Sampai Tanggal.
        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve(now(), $this->record->company_id);
        $this->date_from = $periodStart->toDateString();
        $this->date_to = $periodEnd->toDateString();

        $this->calendarMonth = now()->format('Y-m');

        if ($this->record->id === Auth::id()) {
            $this->fillProfilForm();
        }
    }

    private function fillProfilForm(): void
    {
        $profile = $this->record->profile;

        $this->nickname = $profile?->nickname;
        $this->gender = $profile?->gender;
        $this->marital_status = $profile?->marital_status;
        $this->religion_id = $profile?->religion_id;
        $this->place_of_birth = $profile?->place_of_birth;
        $this->date_of_birth = $profile?->date_of_birth;
        $this->no_nik = $profile?->no_nik;
        $this->no_kk = $profile?->no_kk;
        $this->no_bpjs_kes = $profile?->no_bpjs_kes;
        $this->no_bpjs_ket = $profile?->no_bpjs_ket;
        $this->npwp = $profile?->npwp;
        $this->phone = $profile?->phone;
        $this->whatsapp = $profile?->whatsapp;
        $this->email_pribadi = $profile?->email_pribadi;
        $this->country = $profile?->country;
        $this->province = $profile?->province;
        $this->city = $profile?->city;
        $this->district = $profile?->district;
        $this->village = $profile?->village;
        $this->postal_code = $profile?->postal_code;
        $this->rt = $profile?->rt;
        $this->rw = $profile?->rw;
        $this->address = $profile?->address;
    }

    public function getReligionOptionsProperty()
    {
        return Religions::pluck('name', 'id');
    }

    public function startEditProfil(): void
    {
        abort_unless($this->record->id === Auth::id(), 403);

        $this->fillProfilForm();
        $this->editingProfil = true;
    }

    public function cancelEditProfil(): void
    {
        $this->fillProfilForm();
        $this->editingProfil = false;
    }

    /**
     * Status perkawinan juga SENGAJA tidak masuk sini (sama seperti rekening: cuma HC/admin).
     * Rekening bank SENGAJA tidak masuk sini — dipakai transfer gaji, cuma boleh diubah HC/admin
     * lewat Kepegawaian (lihat diskusi sesi 2026-07-30), bukan self-service. Email AKUN
     * (`users.email`, dipakai login) juga TIDAK bisa diubah dari sini (instruksi user
     * 2026-10-04) - properti & validasinya sengaja tidak ada, supaya request yang di-craft pun
     * tidak bisa mengubahnya. Yang bisa diedit hanya Email Pribadi (`email_pribadi`).
     */
    public function saveProfil()
    {
        abort_unless($this->record->id === Auth::id(), 403);

        $data = $this->validate([
            'nickname' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:laki_laki,perempuan'],
            'religion_id' => ['nullable', 'exists:m_religions,id'],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date'],
            'no_nik' => ['nullable', 'string', 'max:50'],
            'no_kk' => ['nullable', 'string', 'max:50'],
            'no_bpjs_kes' => ['nullable', 'string', 'max:50'],
            'no_bpjs_ket' => ['nullable', 'string', 'max:50'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email_pribadi' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'village' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = $this->record->profile;

        if (! $profile) {
            $this->addError('nickname', 'Profil belum tersedia, hubungi HC.');

            return;
        }

        $profile->update($data);

        $this->record->refresh();
        $this->record->load('profile.religion');
        $this->editingProfil = false;

        Notification::make()->title('Data diri berhasil diperbarui')->success()->send();
    }

    /** Pemilik akun selalu boleh; selain itu WAJIB permission eksplisit
     * `lihat_lampiran_identitas_karyawan` — beda dari sekadar bisa buka Data Diri orang lain
     * (atasan/HC), lihat komentar di CustomPermissionEnum. */
    public function getCanViewProfileDocumentsProperty(): bool
    {
        return $this->record->id === Auth::id()
            || Auth::user()->can(CustomPermissionEnum::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN->value);
    }

    public function getProfileDocumentsProperty()
    {
        if (! $this->canViewProfileDocuments) {
            return collect();
        }

        return UserProfileDocument::query()
            ->where('user_id', $this->record->id)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Kelengkapan Data Diri: field yang sama dengan dashboard Kepegawaian (satu sumber daftar).
     *
     * @return array{percent: int, filled: int, total: int, missing: array<int, string>}
     */
    public function getDataCompletenessProperty(): array
    {
        $profile = $this->record->profile;
        $fields = \App\Livewire\Kepegawaian\KepegawaianDashboardPage::COMPLETENESS_FIELDS;

        $missing = collect($fields)
            ->reject(fn (string $label, string $field) => filled($profile?->{$field}))
            ->values()
            ->all();
        $total = count($fields);

        // Jabatan nakes: dokumen KTP & KK, STR, SIP, dan masa berlakunya ikut dihitung.
        // Cuma cek keberadaan (bukan isi), jadi tidak butuh permission lihat lampiran.
        if ($this->isNakes) {
            $docs = UserProfileDocument::where('user_id', $this->record->id)
                ->whereIn('label', ['KTP', 'KK'])->pluck('label');
            $nakes = $this->record->employee
                ? EmployeeNakesDocument::where('employee_id', $this->record->employee->id)
                    ->where('is_active', true)->get()->keyBy('type')
                : collect();

            foreach (['KTP' => 'Dokumen KTP', 'KK' => 'Dokumen KK'] as $k => $label) {
                $total++;
                $docs->contains($k) || $missing[] = $label;
            }

            // STR: nomor; masa berlaku ikut dihitung kecuali STR-nya seumur hidup (nomor terisi, tanggal kosong).
            $str = $nakes->get('STR');
            $total++;
            filled($str?->nomor) || $missing[] = 'STR';
            $strSeumurHidup = filled($str?->nomor) && $str->berlaku_sampai === null;
            if (! $strSeumurHidup) {
                $total++;
                $str?->berlaku_sampai || $missing[] = 'Masa berlaku STR';
            }

            // SIP: nomor + masa berlaku (mulai & sampai) selalu wajib.
            $sip = $nakes->get('SIP');
            $total += 2;
            filled($sip?->nomor) || $missing[] = 'SIP';
            ($sip?->berlaku_mulai && $sip?->berlaku_sampai) || $missing[] = 'Masa berlaku SIP';
        }

        $filled = $total - count($missing);

        return [
            'percent' => (int) round($filled / $total * 100),
            'filled' => $filled,
            'total' => $total,
            'missing' => $missing,
        ];
    }

    /** STR/SIP/RKK cuma relevan buat nakes (JobPositions::is_nakes) - dipakai buat filter opsi
     * dropdown lampiran DAN guard server-side di addProfileDocument(). */
    public function getIsNakesProperty(): bool
    {
        return (bool) $this->record->activeJobPosition?->jobPosition?->is_nakes;
    }

    /** @return array<int, UserProfileDocumentType> */
    public function getDocumentTypeOptionsProperty(): array
    {
        return UserProfileDocumentType::options($this->isNakes);
    }

    /** Pilih file di slot KTP langsung upload (pola sama HandlesProfilePhotoUpload::updatedPhoto()). */
    public function updatedKtpFile(): void
    {
        $this->uploadProfileDocument(UserProfileDocumentType::KTP, 'ktpFile');
    }

    public function updatedKkFile(): void
    {
        $this->uploadProfileDocument(UserProfileDocumentType::KK, 'kkFile');
    }

    /** STR/SIP yang sedang aktif per tipe (satu per tipe). */
    public function getNakesDocumentsProperty()
    {
        if (! $this->canViewProfileDocuments || ! $this->record->employee) {
            return collect();
        }

        return EmployeeNakesDocument::where('employee_id', $this->record->employee->id)
            ->where('is_active', true)->latest()->get()->unique('type')->keyBy('type');
    }

    /** Riwayat STR/SIP (sudah diganti perpanjangan), terbaru dulu, dikelompokkan per tipe. */
    public function getNakesHistoryProperty()
    {
        if (! $this->canViewProfileDocuments || ! $this->record->employee) {
            return collect();
        }

        return EmployeeNakesDocument::where('employee_id', $this->record->employee->id)
            ->where('is_active', false)->latest()->get()->groupBy('type');
    }

    /**
     * Simpan STR/SIP baru = perpanjangan: dokumen aktif sebelumnya jadi riwayat (tetap bisa dilihat),
     * yang baru jadi aktif. Data yang sudah tersimpan TIDAK bisa diubah (tidak ada edit/hapus).
     * Konfirmasi ke karyawan dilakukan di blade (wire:confirm).
     */
    public function saveNakesDocument(string $type): void
    {
        abort_unless($this->record->id === Auth::id() && $this->isNakes, 403);
        abort_unless(in_array($type, ['STR', 'SIP'], true), 404);
        $employee = $this->record->employee;
        abort_unless($employee, 404);

        // SIP lengkap yang masih lama berlakunya belum boleh diganti (tombol Perpanjang juga disembunyikan di blade).
        if ($type === 'SIP') {
            $aktif = EmployeeNakesDocument::where('employee_id', $employee->id)->where('type', 'SIP')->where('is_active', true)->first();
            abort_if(
                $aktif && filled($aktif->nomor) && $aktif->berlaku_sampai
                    && $aktif->sisaHari() > EmployeeNakesDocument::PERPANJANG_SIP_HARI,
                403
            );
        }

        $prop = strtolower($type) . 'File';
        $lifetime = $type === 'STR' && (bool) ($this->nakesForm[$type]['seumur_hidup'] ?? false); // SIP selalu ada masa berlaku

        $this->validate([
            "nakesForm.$type.nomor" => ['required', 'string', 'max:100'],
            "nakesForm.$type.berlaku_mulai" => [$type === 'SIP' ? 'required' : 'nullable', 'date'],
            "nakesForm.$type.berlaku_sampai" => [$lifetime ? 'nullable' : 'required', 'date', 'after_or_equal:nakesForm.' . $type . '.berlaku_mulai'],
            $prop => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [], [
            "nakesForm.$type.nomor" => "Nomor $type",
            "nakesForm.$type.berlaku_mulai" => 'Berlaku mulai',
            "nakesForm.$type.berlaku_sampai" => 'Berlaku sampai',
            $prop => "File $type",
        ]);

        $file = $this->$prop;
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = 'profile-documents/' . $this->record->id . '/' . Str::ulid() . '.' . $ext;
        Storage::disk('local')->put($path, Crypt::encryptString(file_get_contents($file->getRealPath())));

        DB::transaction(function () use ($employee, $type, $lifetime, $path) {
            EmployeeNakesDocument::where('employee_id', $employee->id)->where('type', $type)
                ->where('is_active', true)->update(['is_active' => false]);

            EmployeeNakesDocument::create([
                'employee_id' => $employee->id,
                'type' => $type,
                'nomor' => $this->nakesForm[$type]['nomor'],
                'berlaku_mulai' => $lifetime ? null : ($this->nakesForm[$type]['berlaku_mulai'] ?: null),
                'berlaku_sampai' => $lifetime ? null : $this->nakesForm[$type]['berlaku_sampai'],
                'file_path' => $path,
                'is_active' => true,
            ]);
        });

        $this->$prop = null;
        $this->nakesForm[$type] = ['nomor' => '', 'berlaku_mulai' => '', 'berlaku_sampai' => '', 'seumur_hidup' => false];
        $this->dispatch('nakes-saved', type: $type);
        Notification::make()->title("$type tersimpan")->success()->send();
    }

    public function updatedRkkFile(): void
    {
        $this->uploadProfileDocument(UserProfileDocumentType::RKK, 'rkkFile');
    }

    /**
     * Upload ulang tipe yang sama MENGGANTI baris lama (upsert), bukan menambah baris baru
     * (instruksi user 2026-10-05). Satu slot tetap per tipe di blade (kayak halaman Pengaturan),
     * bukan lagi form pilih-tipe-lalu-tambah.
     */
    private function uploadProfileDocument(UserProfileDocumentType $type, string $property): void
    {
        abort_unless($this->record->id === Auth::id(), 403);

        // Jangan cuma sembunyikan slot STR/SIP/RKK di UI - tolak juga di server, request bisa
        // dimanipulasi manual.
        abort_if($type->nakesOnly() && ! $this->isNakes, 403);
        abort_if($type === UserProfileDocumentType::RKK || $type->nakesOnly(), 403); // STR/SIP: saveNakesDocument()

        $this->validate([
            $property => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file */
        $file = $this->$property;

        // Lampiran data diri (KTP/KK/dsb) disimpan TERENKRIPSI di disk privat 'local'
        // (storage/app/private, tidak web-accessible) — beda dari lampiran lain yang plaintext
        // di 'public'. Dilayani lewat route profil-saya.lampiran.show yang decrypt + cek izin.
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = 'profile-documents/' . $this->record->id . '/' . Str::ulid() . '.' . $ext;

        Storage::disk('local')->put(
            $path,
            Crypt::encryptString(file_get_contents($file->getRealPath()))
        );

        $existing = UserProfileDocument::query()
            ->where('user_id', $this->record->id)
            ->where('label', $type->value)
            ->first();

        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
            Storage::disk('public')->delete($existing->file_path);
            $existing->update(['file_path' => $path]);
        } else {
            UserProfileDocument::create([
                'user_id' => $this->record->id,
                'label' => $type->value,
                'file_path' => $path,
            ]);
        }

        $this->$property = null;

        Notification::make()->title('Dokumen berhasil diunggah')->success()->send();
    }

    public function deleteProfileDocument(string $documentId): void
    {
        abort_unless($this->record->id === Auth::id(), 403);

        $document = UserProfileDocument::query()
            ->where('user_id', $this->record->id)
            ->findOrFail($documentId);

        // Lampiran baru ada di 'local' (terenkripsi), lampiran lama di 'public' — hapus dua-duanya.
        Storage::disk('local')->delete($document->file_path);
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        Notification::make()->title('Dokumen dihapus')->success()->send();
    }

    /** Tab "Pembinaan" — karyawan itu sendiri, atasan langsung/hierarki-nya (instruksi user
     * 2026-09-26), ATAU pemegang permission modul Pembinaan Karyawan (SISTEM_PEMBINAAN_KARYAWAN).
     * Isinya CUMA Coaching — Counseling sengaja tidak ditampilkan di sini (instruksi user). */
    public function getCanViewPembinaanTabProperty(): bool
    {
        // Profil sendiri: daftar pembinaan ada di halaman "Pembinaan Saya", bukan tab ini.
        return ! $this->isOwnProfile && ($this->canViewMeritWidget
            || app(OrgHierarchyService::class)
                ->subordinates(Auth::user())
                ->whereKey($this->record->id)
                ->exists());
    }

    /** Gerbang lama tab Pembinaan (pemilik akun / SISTEM_PEMBINAAN_KARYAWAN) — sekarang khusus
     * widget Merit Point + SP, atasan biasa tetap TIDAK lihat. */
    public function getCanViewMeritWidgetProperty(): bool
    {
        return $this->record->id === Auth::id()
            || Auth::user()->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value);
    }

    /** Riwayat coaching di mana karyawan ini yang dibina (user_id). Di-scope ke record juga di
     * sini supaya tidak bocor walau blade lupa gerbang. */
    public function getMyCoachingsProperty()
    {
        if (! $this->canViewPembinaanTab) {
            return collect();
        }

        return \App\Models\Coaching\Coaching::query()
            ->where('user_id', $this->record->id)
            ->with('coachBy')
            ->latest()
            ->get();
    }

    /**
     * Kill-switch GLOBAL sementara (instruksi user 2026-09-23) - widget Merit Point + SP di tab
     * Karir belum siap tampil ke semua company (permission SISTEM_PEMBINAAN_KARYAWAN belum
     * di-assign ke role selain Super Admin, lihat percakapan Hak Akses). Set true lagi kalau
     * sudah siap rilis - fitur di baliknya (SpService, MeritPointService, dst) tidak disentuh,
     * ini murni sembunyikan tampilan.
     */
    public function getShowSpMeritWidgetProperty(): bool
    {
        return true;
    }

    /**
     * Widget Merit Point + SP di tab Karir (planning/2026-09-23-sp-dan-merit-point.md §3.6) -
     * cache tampilan, sama gerbang dengan tab Pembinaan (canViewPembinaanTab: pemilik akun atau
     * pemegang SISTEM_PEMBINAAN_KARYAWAN). Baca dari user_hr_balances (cache) - SESUAI DESAIN,
     * bukan live-query, widget ini murni tampilan bukan validasi bisnis (lihat §3.5).
     */
    public function getHrBalanceProperty(): ?UserHrBalance
    {
        if (! $this->showSpMeritWidget || ! $this->canViewMeritWidget) {
            return null;
        }

        return UserHrBalance::where('user_id', $this->record->id)->first();
    }

    /** Merit Point aktif per company lewat Pengaturan Kepegawaian (KepegawaianSettingEnum::MERIT_POINT_ENABLED).
     * Fallback kalau baris settings belum ada = default enum (false), sama seperti MeritPointRuleResource &
     * MeritPointTransactionResource - dulu fallback-nya true sehingga "Poin Anda" tampil walau belum pernah diaktifkan. */
    public function getMeritPointEnabledProperty(): bool
    {
        return (bool) Setting::resolve(
            KepegawaianSettingEnum::MERIT_POINT_ENABLED->value,
            $this->record->company_id,
            filter_var(KepegawaianSettingEnum::MERIT_POINT_ENABLED->default(), FILTER_VALIDATE_BOOLEAN)
        );
    }

    // ── Riwayat Merit Point (panel, bukan halaman terpisah - instruksi user 2026-09-25) ──
    public bool $showMeritHistoryModal = false;

    public function getMeritHistoryProperty()
    {
        if (! $this->showMeritHistoryModal) {
            return collect();
        }

        return \App\Models\MeritPoint\MeritPointTransaction::where('user_id', $this->record->id)
            ->with('rule')
            ->latest('created_at')
            ->get();
    }

    public function openMeritHistoryModal(): void
    {
        abort_unless($this->meritPointEnabled && $this->record->id === Auth::id(), 403);

        $this->showMeritHistoryModal = true;
    }

    public function closeMeritHistoryModal(): void
    {
        $this->showMeritHistoryModal = false;
    }

    // ── Info Aturan Merit Point (panel read-only, instruksi user 2026-09-27) ── Daftar semua
    // rule aktif (manual & otomatis) company ini, supaya siapa pun yang lihat widget - diri
    // sendiri ATAU orang lain - tahu persis kejadian apa dapat berapa poin (mis. "Terlambat -1",
    // "Ikut Pelatihan +5"), tanpa perlu buka Pengaturan Kepegawaian. Cuma informasi, tidak ada
    // permission khusus - siapa pun yang bisa lihat widget ($showMeritBeside di blade) boleh buka.
    public bool $showMeritRulesInfoModal = false;

    public function getMeritRulesInfoProperty()
    {
        if (! $this->showMeritRulesInfoModal) {
            return collect();
        }

        return \App\Models\MeritPoint\MeritPointRule::where('company_id', $this->record->company_id)
            ->where('is_active', true)
            ->orderBy('trigger_type')
            ->orderBy('name')
            ->get();
    }

    public function openMeritRulesInfoModal(): void
    {
        abort_unless($this->meritPointEnabled, 403);

        $this->showMeritRulesInfoModal = true;
    }

    public function closeMeritRulesInfoModal(): void
    {
        $this->showMeritRulesInfoModal = false;
    }

    // ── Transaksi Merit Point Modal ── Rule ATAU manual bebas (dikembalikan 2026-09-27 - user
    // sempat minta manual dihapus, tapi berubah pikiran, tab manual balik lagi).
    public bool $showMeritTransactionModal = false;
    public string $meritTab = 'rule';
    public ?string $merit_rule_id = null;
    public string $merit_manual_type = 'add';
    public ?int $merit_points = null;
    public string $merit_reason = '';

    // Satu permission custom (BERI_MERIT_POINT), bukan lagi 7 verb Shield Create:MeritPointTransaction
    // dkk (MeritPointTransactionResource dihapus 2026-09-27) - Super Admin & role Pembinaan sudah
    // di-grant literal lewat migration, jadi cukup 1 cek di sini (define_via_gate=false, tidak ada
    // bypass otomatis, lihat App\Enum\CustomPermissionEnum::BERI_MERIT_POINT). Tidak boleh beri
    // poin ke DIRI SENDIRI walau punya permission-nya (instruksi user 2026-09-27) - dicek di sini
    // (bukan cuma sembunyikan tombol) karena property ini juga jadi abort_unless() di
    // openMeritTransactionModal()/saveMeritTransaction(), jadi otomatis kena di server juga.
    public function getCanCreateMeritTransactionProperty(): bool
    {
        $user = Auth::user();

        return $user && $user->id !== $this->record->id && $user->can(CustomPermissionEnum::BERI_MERIT_POINT->value);
    }

    /** Cuma rule MANUAL - rule Otomatis (trigger_type=automatic) dikelola sendiri oleh sistem
     * lewat MeritPointService::applyEvent(), tidak boleh dipilih untuk diberikan manual di sini
     * (bisa dobel dengan yang otomatis). */
    public function getMeritPointRulesProperty()
    {
        $companyId = $this->record->company_id;

        return \App\Models\MeritPoint\MeritPointRule::query()
            ->where('is_active', true)
            ->where('trigger_type', \App\Enum\MeritPoint\MeritPointTriggerType::MANUAL->value)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get();
    }

    public function openMeritTransactionModal(): void
    {
        abort_unless($this->meritPointEnabled && $this->canCreateMeritTransaction, 403);

        $this->resetMeritForm();
        $this->showMeritTransactionModal = true;
    }

    public function closeModalMeritTransaction(): void
    {
        $this->showMeritTransactionModal = false;
        $this->resetMeritForm();
    }

    private function resetMeritForm(): void
    {
        $this->meritTab = 'rule';
        $this->merit_rule_id = null;
        $this->merit_manual_type = 'add';
        $this->merit_points = null;
        $this->merit_reason = '';
        $this->resetErrorBag();
    }

    public function updatedMeritRuleId($value): void
    {
        if ($value) {
            $rule = \App\Models\MeritPoint\MeritPointRule::find($value);
            if ($rule && empty($this->merit_reason)) {
                $this->merit_reason = $rule->name;
            }
        }
    }

    public function saveMeritTransaction(): void
    {
        abort_unless($this->meritPointEnabled && $this->canCreateMeritTransaction, 403);

        if ($this->meritTab === 'rule') {
            $this->validate([
                'merit_rule_id' => ['required', 'exists:merit_point_rules,id'],
                'merit_reason' => ['required', 'string', 'max:500'],
            ], [
                'merit_rule_id.required' => 'Silakan pilih aturan Merit Point.',
                'merit_rule_id.exists' => 'Aturan tidak ditemukan.',
                'merit_reason.required' => 'Keterangan/catatan wajib diisi.',
            ]);

            // findOrFail lewat meritPointRules (bukan MeritPointRule::findOrFail polos) - jaga
            // rule yang dipilih benar rule MANUAL milik company ini, bukan hasil payload
            // dimanipulasi (mis. ID rule Otomatis company lain).
            $rule = $this->meritPointRules->firstWhere('id', $this->merit_rule_id);

            if (! $rule) {
                $this->addError('merit_rule_id', 'Aturan tidak ditemukan.');

                return;
            }

            // effectivePoints(), bukan $rule->points polos - rule Reward yang punya bonus_points
            // dipetakan ke poin bonus (bukan poin biasa) kalau saldo karyawan sudah >= 100.
            $points = app(\App\Services\MeritPoint\MeritPointService::class)->effectivePoints($rule, $this->record->id);
            $ruleId = $rule->id;
        } else {
            $this->validate([
                'merit_manual_type' => ['required', 'in:add,subtract'],
                'merit_points' => ['required', 'integer', 'min:1', 'max:100'],
                'merit_reason' => ['required', 'string', 'max:500'],
            ], [
                'merit_manual_type.required' => 'Pilih jenis transaksi (tambah/kurang).',
                'merit_points.required' => 'Jumlah poin wajib diisi.',
                'merit_points.integer' => 'Jumlah poin harus berupa angka bulat.',
                'merit_points.min' => 'Poin minimal 1.',
                'merit_points.max' => 'Poin maksimal 100.',
                'merit_reason.required' => 'Keterangan/catatan wajib diisi.',
            ]);

            $points = $this->merit_manual_type === 'subtract'
                ? -abs((int) $this->merit_points)
                : abs((int) $this->merit_points);
            $ruleId = null;
        }

        app(\App\Services\MeritPoint\MeritPointService::class)->addTransaction([
            'user_id' => $this->record->id,
            'rule_id' => $ruleId,
            'points' => $points,
            'reason' => $this->merit_reason,
            'created_by' => Auth::id(),
        ]);

        $this->showMeritTransactionModal = false;
        $this->resetMeritForm();
        $this->record->refresh();

        Notification::make()
            ->title('Transaksi Merit Point berhasil disimpan')
            ->success()
            ->send();
    }

    /** Pemilik akun selalu boleh; selain itu HC (`sistem_kepegawaian`, dibatasi company sendiri
     * sama seperti EmployeeResource) atau yang bisa lihat lintas company — BUKAN atasan biasa,
     * sekalipun atasan bisa buka Data Diri staff-nya untuk tab lain (lihat canManage()). */
    public function getIsOwnProfileProperty(): bool
    {
        return $this->record->id === Auth::id();
    }

    /** Boleh hitung ulang presensi profil ini: pemilik akun, atau pemegang izin lihat rekap presensi
     * (view_report_data_absensi) - dibatasi company sendiri kecuali bisa lihat lintas company,
     * sama seperti RekapPresensiPage. */
    public function getCanResyncProperty(): bool
    {
        if ($this->isOwnProfile) {
            return true;
        }

        $user = Auth::user();

        return $user->can(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value)
            && ($user->canFilterByCompany() || $user->company_id === $this->record->company_id);
    }

    /** Tombol "Hitung Ulang Presensi": boleh hitung ulang + rentang terpilih tidak lebih dari RESYNC_MAX_DAYS hari. */
    public function getCanResyncPeriodProperty(): bool
    {
        return $this->canResync
            && $this->date_from && $this->date_to && $this->date_from <= $this->date_to
            && Carbon::parse($this->date_from)->diffInDays(Carbon::parse($this->date_to)) + 1 <= self::RESYNC_MAX_DAYS;
    }

    public function syncMyPeriod(): void
    {
        abort_unless($this->canResync, 403);

        $this->resyncOwnRange($this->date_from, $this->date_to, $this->record);
    }

    public function getCanViewAuditHistoryProperty(): bool
    {
        if ($this->record->id === Auth::id()) {
            return true;
        }

        $user = Auth::user();

        return $user->canBrowseAllCompanies()
            || ($user->can(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value) && $user->company_id === $this->record->company_id);
    }

    // ── Riwayat Perubahan Data (panel geser di tab Data Diri, bukan tab terpisah - 2026-09-26) ──
    public bool $showAuditHistoryPanel = false;

    public function openAuditHistoryPanel(): void
    {
        abort_unless($this->canViewAuditHistory, 403);

        $this->resetPage('riwayatPage');
        $this->showAuditHistoryPanel = true;
    }

    public function closeAuditHistoryPanel(): void
    {
        $this->showAuditHistoryPanel = false;
    }

    /**
     * Gabungan riwayat User + UserProfiles (LogsFieldAuditChanges di kedua model menulis ke
     * `audit_logs` dengan key berprefix user_id yang sama) — 1 query cukup, apa pun tabel asal
     * fieldnya. Dipaginasi (page name khusus 'riwayatPage' supaya tidak bentrok kalau nanti ada
     * paginator lain di komponen ini).
     */
    public function getAuditHistoryProperty()
    {
        if (! $this->canViewAuditHistory) {
            return collect();
        }

        return AuditLog::query()
            ->where('module', 'data_karyawan')
            ->where('key', 'like', $this->record->id . ':%')
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'riwayatPage');
    }

    /** Nilai audit tersimpan mentah (ULID FK / "1"/""); diterjemahkan ke nama saat ditampilkan. */
    public function auditValue(AuditLog $entry, ?string $value): string
    {
        $field = Str::after($entry->key, ':');

        if ($field === 'is_active') {
            return $value ? 'Aktif' : 'Nonaktif';
        }

        if ($value === null || $value === '') {
            return '(kosong)';
        }

        $model = [
            'religion_id' => Religions::class,
            'job_position_id' => \App\Models\Master\JobPositions::class,
            'pangkat_id' => \App\Models\Master\Pangkats::class,
            'kelas_id' => \App\Models\Master\Kelas::class,
            'employee_type_id' => \App\Models\Master\EmployeeTypes::class,
            'company_id' => \App\Models\Master\Companies::class,
        ][$field] ?? null;

        return $model ? ($model::withoutGlobalScopes()->whereKey($value)->value('name') ?? $value) : $value;
    }


    /** Tombol "Hapus Akun Karyawan" di tab Data Diri — pakai permission Shield standar
     * Delete:User (UserPolicy::delete, sama gerbang dengan tombol Hapus di admin
     * EmployeeResource), bukan permission manual baru. Tidak boleh untuk diri sendiri, dan
     * akun belum di-nonaktifkan. */
    public function getCanDeleteEmployeeProperty(): bool
    {
        return $this->record->id !== Auth::id()
            && $this->record->is_active
            && Auth::user()->can('delete', $this->record);
    }

    /** ponytail: cuma soft delete (users.deleted_at) + is_active=false — jalur manual sementara,
     * BUKAN alur PHK resmi (tidak menutup jabatan/riwayat seperti UserTransitionService). */
    public function deleteEmployee()
    {
        abort_unless($this->canDeleteEmployee, 403);

        $this->record->update(['is_active' => false]);
        $this->record->delete();

        Notification::make()->title('Akun karyawan dihapus (soft delete)')->success()->send();

        return redirect()->route('kepegawaian.karyawan');
    }

    protected function canManage(User $record): bool
    {
        $user = Auth::user();

        // Karyawan boleh lihat data dirinya sendiri lewat halaman ini juga (tombol "Data Diri"
        // di Dashboard) — bukan cuma atasan yang lihat staff-nya.
        if ($record->id === $user->id) {
            return true;
        }

        if ($user->canBrowseAllCompanies()) {
            return true;
        }

        // HC/Kepegawaian: company sendiri (konsisten dengan scoping EmployeeResource di admin),
        // ATAU lintas company kalau pegang LIHAT_PERUSAHAAN_LAIN. Tab Penggajian & No. Rekening
        // tetap self-only (digerbangi $isSelf di blade), tidak ikut kebuka lewat sini.
        if ($user->can(\App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value)
            && ($user->company_id === $record->company_id || $user->canFilterByCompany())) {
            return true;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->id)
            ->exists();
    }

    // Tab Absensi/Riwayat Pengajuan/Dokumen/Penggajian dibatasi HANYA kalau tipe karyawan
    // belum diisi sama sekali (null) - ketiga kategori (Tetap/Kontrak/Orientasi) semuanya
    // dapat akses penuh begitu tipe karyawannya di-set, apa pun kategorinya. Keputusan
    // sengaja: null dianggap "belum lengkap datanya", bukan diam-diam dapat akses penuh.
    public function getShowExtraTabsProperty(): bool
    {
        return $this->record->profile?->employeeType?->category !== null;
    }

    /** orientasi < kontrak < tetap — buat ribbon "sudah sampai fase mana" di kartu Status Kepegawaian. */
    public function phaseRank(?string $phase): int
    {
        return match ($phase) {
            EmployeeTypeCategory::ORIENTASI->value => 1,
            EmployeeTypeCategory::KONTRAK->value => 2,
            EmployeeTypeCategory::TETAP->value => 3,
            default => 0,
        };
    }

    /** Rasio 0–1 posisi hari ini di antara mulai & berakhir fase — buat progress bar kartu status. */
    private function phaseProgress(Carbon $start, Carbon $end): ?float
    {
        $total = $start->diffInDays($end);

        if ($total <= 0) {
            return null;
        }

        return max(0.0, min(1.0, $start->diffInDays(Carbon::now()) / $total));
    }

    /**
     * Perjalanan status kepegawaian: Bergabung → Orientasi (+perpanjangan) → Kontrak
     * (+perpanjangan) → Karyawan Tetap. `nodes` dari ledger user_orientations + user_contracts
     * (append-only), `summary` = fase berjalan buat tampilan ringkas kartu di tab Karir.
     * Read-only, di-scope ke $this->record.
     *
     * @return array{category: ?string, summary: array{headline: ?string, sub: ?string, ended: ?\Carbon\Carbon, progress: ?float}, nodes: list<array{phase: string, title: string, start: mixed, end: mixed, meta: ?string}>}
     */
    public function getEmploymentJourneyProperty(): array
    {
        $profile = $this->record->profile;
        // EmployeeTypes.category di-cast ke EmployeeTypeCategory enum — normalize ke string
        // biar konsisten dipakai buat perbandingan, phaseRank(?string), & match() di blade.
        $rawCategory = $profile?->employeeType?->category;
        $category = $rawCategory instanceof EmployeeTypeCategory ? $rawCategory->value : $rawCategory;
        $join = $profile?->joining_date ? Carbon::parse($profile->joining_date) : null;

        $orientations = $this->record->employee?->orientations()->orderBy('start_date')->get() ?? collect();
        $contracts = $this->record->employee?->contracts()->with('employeeType')->orderBy('start_date')->get() ?? collect();

        $nodes = [];

        if ($join) {
            $nodes[] = ['phase' => 'join', 'title' => 'Bergabung', 'start' => $join, 'end' => null, 'meta' => null];
        }

        foreach ($orientations as $o) {
            $nodes[] = [
                'phase' => 'orientasi',
                'title' => $o->source === 'extension' ? 'Perpanjangan orientasi' : 'Mulai orientasi',
                'start' => $o->start_date,
                'end' => $o->end_date,
                'meta' => $o->duration_months ? $o->duration_months . ' bulan' : null,
            ];
        }

        $tetapStart = null;
        $kontrakCount = 0;

        foreach ($contracts as $c) {
            $cCat = $c->employeeType?->category;
            $cCat = $cCat instanceof EmployeeTypeCategory ? $cCat->value : $cCat;

            if ($cCat === EmployeeTypeCategory::TETAP->value) {
                $tetapStart ??= $c->start_date;
                $nodes[] = ['phase' => 'tetap', 'title' => 'Diangkat karyawan tetap', 'start' => $c->start_date, 'end' => null, 'meta' => null];

                continue;
            }

            $kontrakCount++;
            $nodes[] = [
                'phase' => 'kontrak',
                'title' => $kontrakCount === 1 ? 'Kontrak pertama' : "Perpanjangan kontrak ke-{$kontrakCount}",
                'start' => $c->start_date,
                'end' => $c->end_date,
                'meta' => null,
            ];
        }

        // Pengangkatan tetap biasanya cuma nge-stempel user_profiles.tmt_tetap (+ flip tipe),
        // tanpa nulis baris user_contracts kategori tetap — kalau begitu, tambah node-nya dari
        // tmt_tetap supaya timeline tetap kelihatan sampai fase Tetap.
        if ($tetapStart === null && $profile?->tmt_tetap) {
            $tetapStart = $profile->tmt_tetap;
            $nodes[] = ['phase' => 'tetap', 'title' => 'Diangkat karyawan tetap', 'start' => $profile->tmt_tetap, 'end' => null, 'meta' => null];
        }

        $summary = ['headline' => null, 'sub' => null, 'ended' => null, 'progress' => null];

        if ($category === EmployeeTypeCategory::ORIENTASI->value) {
            $cur = $this->record->employee?->currentOrientation;
            $summary['headline'] = 'Orientasi';
            if ($cur?->end_date) {
                $summary['ended'] = Carbon::parse($cur->end_date);
                $summary['sub'] = 'Berakhir ' . $summary['ended']->translatedFormat('d M Y');
                $summary['progress'] = $cur->start_date ? $this->phaseProgress(Carbon::parse($cur->start_date), $summary['ended']) : null;
            }
        } elseif ($category === EmployeeTypeCategory::KONTRAK->value) {
            $cur = $this->record->employee?->currentContract;
            $summary['headline'] = $kontrakCount > 1 ? "Kontrak (perpanjangan ke-{$kontrakCount})" : 'Kontrak';
            if ($cur?->end_date) {
                $summary['ended'] = Carbon::parse($cur->end_date);
                $summary['sub'] = 'Berakhir ' . $summary['ended']->translatedFormat('d M Y');
                $summary['progress'] = $cur->start_date ? $this->phaseProgress(Carbon::parse($cur->start_date), $summary['ended']) : null;
            }
        } elseif ($category === EmployeeTypeCategory::TETAP->value) {
            // TMT resmi (user_profiles.tmt_tetap) diutamakan, fallback ke baris kontrak (employee_contracts)
            // kategori tetap pertama. SENGAJA tidak fallback ke Tanggal Bergabung di sini
            // (beda dari fase lain) - "Sejak [tanggal bergabung]" salah paham, seolah TMT
            // tetapnya persis hari masuk kerja, padahal cuma belum tercatat.
            $since = $profile?->tmt_tetap ? Carbon::parse($profile->tmt_tetap)
                : ($tetapStart ? Carbon::parse($tetapStart) : null);
            $summary['headline'] = 'Tetap';
            $summary['sub'] = $since
                ? 'Sejak ' . $since->translatedFormat('d M Y')
                : 'TMT tetap belum tercatat' . ($join ? ' (bergabung ' . $join->translatedFormat('d M Y') . ')' : '');
        } else {
            $summary['headline'] = 'Status kepegawaian belum diatur';
        }

        // Ledger orientasi/kontrak belum keisi (mis. data lama, atau baru di-set tipe
        // karyawannya) — minimal tampilkan Tanggal Bergabung, jangan headline melompong.
        if ($summary['sub'] === null && $join) {
            $summary['sub'] = 'Bergabung ' . $join->translatedFormat('d M Y');
        }

        return ['category' => $category, 'summary' => $summary, 'nodes' => $nodes];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [
            // Riwayat Pengajuan di tab Kehadiran HANYA menampilkan yang sudah disetujui (instruksi user
            // 2026-09-26) - filter status dihapus dari UI, jadi dikunci di sini, bukan property publik
            // yang bisa dioprek dari client.
            'status' => AttendanceReqStatus::APPROVED->value,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ];
    }

    public function getRequestsProperty()
    {
        return app(AttendaceReqService::class)
            ->getListAttendanceRequestsByUserId($this->record->id, $this->filters());
    }

    /**
     * @return array{leave: int, permission: int, overtime: int}
     */
    public function getStatsProperty(): array
    {
        $requests = $this->requests;

        return [
            'leave' => $requests->where('type', 'leave')->count(),
            'permission' => $requests->where('type', 'permission')->count(),
            'overtime' => $requests->where('type', 'overtime')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getPresenceStatsProperty(): array
    {
        $counts = AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $case) => [$case->value => $counts[$case->value] ?? 0])
            ->toArray();
    }

    public function getTodayPresenceProperty(): ?AttendanceRecords
    {
        return AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->where('date', now()->toDateString())
            ->first();
    }

    public function getTodayAttendanceStatusProperty(): array
    {
        return $this->todayAttendanceStatus($this->record, $this->todayPresence);
    }

    public function getPresenceRecordsProperty()
    {
        return AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->orderByDesc('date')
            ->get();
    }

    public function getCalendarMonthLabelProperty(): string
    {
        return Carbon::parse($this->calendarMonth . '-01')->locale('id')->translatedFormat('F Y');
    }

    public function prevMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->addMonthNoOverflow()->format('Y-m');
    }

    /**
     * Grid kalender bulan berjalan, Senin-Minggu, sama pola dengan HolidaysPage - sel null buat
     * padding sebelum tanggal 1 & setelah tanggal terakhir. Tiap sel bawa presensi hari itu
     * (attendance_records) DAN pengajuan yang menyentuh hari itu (approved/submitted/in_review -
     * bukan cuma approved, biar kelihatan juga yang masih proses persetujuan).
     *
     * @return array<int, array{date: Carbon, attendance: ?AttendanceRecords, requests: \Illuminate\Support\Collection}|null>
     */
    public function getCalendarDaysProperty(): array
    {
        $start = Carbon::parse($this->calendarMonth . '-01');
        $end   = $start->copy()->endOfMonth();

        $attendanceByDate = AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceRecords $r) => Carbon::parse($r->date)->toDateString());

        $requestsByDate = $this->requestMarkersForMonth($start, $end);

        $leadingBlanks = $start->dayOfWeekIso - 1; // Senin=1 -> 0 blank, Minggu=7 -> 6 blank
        $cells = array_fill(0, $leadingBlanks, null);

        for ($day = 1; $day <= $end->day; $day++) {
            $date = $start->copy()->day($day);
            $dateKey = $date->toDateString();
            $cells[] = [
                'date'       => $date,
                'attendance' => $attendanceByDate->get($dateKey),
                'requests'   => $requestsByDate->get($dateKey, collect()),
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return $cells;
    }

    /**
     * Kumpulkan marker pengajuan (cuti/izin/lembur, approved+submitted+in_review) yang
     * menyentuh bulan ini, dikelompokkan per tanggal. Cuti dipakai rentang start_date/end_date
     * (null end_date = 1 hari), izin/lembur dipakai tanggal tunggal per detail baris.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection>
     */
    private function requestMarkersForMonth(Carbon $start, Carbon $end): \Illuminate\Support\Collection
    {
        $activeStatuses = ['approved', 'submitted', 'in_review'];
        $markers = collect();

        $addMarker = function (string $dateKey, string $type, string $status) use (&$markers) {
            $existing = $markers->get($dateKey, collect());
            $markers->put($dateKey, $existing->push(['type' => $type, 'status' => $status]));
        };

        // Lembur sengaja tidak ditandai di kalender ini (2026-08-15, instruksi user) - cuma
        // Cuti/Izin, lembur bukan alasan "tidak presensi normal" seperti cuti/izin.
        $requests = AttendanceRequests::query()
            ->where('user_id', $this->record->id)
            ->whereIn('status', $activeStatuses)
            ->whereIn('type', ['leave', 'permission'])
            ->with(['leaveDetails', 'permissionDetails'])
            ->get();

        foreach ($requests as $request) {
            foreach ($request->leaveDetails as $detail) {
                $rangeStart = Carbon::parse($detail->start_date);
                $rangeEnd   = $detail->end_date ? Carbon::parse($detail->end_date) : $rangeStart->copy();

                $clampStart = $rangeStart->lt($start) ? $start->copy() : $rangeStart;
                $clampEnd   = $rangeEnd->gt($end) ? $end->copy() : $rangeEnd;

                for ($d = $clampStart->copy(); $d->lte($clampEnd); $d->addDay()) {
                    $addMarker($d->toDateString(), 'leave', $request->status);
                }
            }

            foreach ($request->permissionDetails as $detail) {
                $date = Carbon::parse($detail->date);
                if ($date->between($start, $end)) {
                    $addMarker($date->toDateString(), 'permission', $request->status);
                }
            }
        }

        return $markers;
    }

    /**
     * Riwayat gaji yang sudah dibayarkan — cuma dipanggil dari tab "Penggajian" yang cuma
     * tampil kalau $isSelf (lihat blade), tapi tetap di-scope ke $this->record->id di sini
     * juga supaya computed property ini sendiri tidak pernah bocor data payroll orang lain.
     */
    public function getMyPayrollHistoryProperty()
    {
        return Payroll::query()
            ->whereIn('employee_id', Employee::query()->select('id')->where('user_id', $this->record->id))
            ->where('is_paid', true)
            ->with('payrollPeriod')
            ->get()
            ->sortByDesc(fn (Payroll $p) => $p->payrollPeriod?->end_date)
            ->values();
    }

    private function keterangan(AttendanceRequests $record): string
    {
        return match ($record->type) {
            'leave' => $record->leaveDetails->first()?->leave_type ?? 'Cuti',
            'permission' => $record->permissionDetails->first()?->permission_type ?? 'Izin',
            'overtime' => $record->overtimeDetails->first()?->overtime_type ?? 'Lembur',
            default => '-',
        };
    }

    public function render()
    {
        $title = $this->record->id === Auth::id()
            ? 'Profil Saya'
            : "MyStaff - {$this->record->full_name}";

        return view('livewire.employee-statistics')
            ->layout('components.layout', ['title' => $title]);
    }
}
