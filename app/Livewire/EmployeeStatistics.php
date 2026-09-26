<?php

namespace App\Livewire;

use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\EmployeeTypeCategory;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Helpers\PermissionHelper;
use App\Livewire\Concerns\HandlesProfilePhotoUpload;
use App\Livewire\Concerns\ResolvesTodayAttendanceStatus;
use App\Models\AuditLog;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Religions;
use App\Models\Payroll\Payroll;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserDocuments;
use App\Models\User\UserProfileDocument;
use App\Models\UserHrBalance;
use App\Services\Attendance\AttendaceReqService;
use App\Services\Attendance\AttendancePeriodResolver;
use App\Services\OrgHierarchyService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class EmployeeStatistics extends Component
{
    use ResolvesTodayAttendanceStatus;
    use HandlesProfilePhotoUpload;
    use WithFileUploads;
    use WithPagination;

    public User $record;

    public ?string $date_from = null;
    public ?string $date_to = null;

    public string $calendarMonth;

    // ── Edit Data Diri (self-service, lihat saveProfil()) ──
    public bool $editingProfil = false;
    public ?string $email = null;
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

    // ── Tambah lampiran (label bebas, bukan tipe tetap) ──
    public string $newDocumentLabel = '';
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $newDocumentFile = null;

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

        $this->email = $this->record->email;
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
     * Rekening bank SENGAJA tidak masuk sini — dipakai transfer gaji, cuma boleh diubah HC/admin
     * lewat Kepegawaian (lihat diskusi sesi 2026-07-30), bukan self-service. Email BEDA
     * penanganan dari field profil lain: dia kolom `users.email` (dipakai login), jadi
     * disimpan terpisah dari $profile->update(), dan kalau nilainya benar-benar berubah,
     * user di-logout paksa sesudahnya (lihat peringatan di blade) supaya session lama yang
     * masih terikat ke email sebelumnya tidak menggantung.
     */
    public function saveProfil()
    {
        abort_unless($this->record->id === Auth::id(), 403);

        $data = $this->validate([
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($this->record->id)],
            'nickname' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:laki_laki,perempuan'],
            'marital_status' => ['nullable', 'string', 'max:50'],
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

        $newEmail = $data['email'];
        unset($data['email']);
        $emailChanged = $newEmail !== $this->record->email;

        $profile->update($data);

        if ($emailChanged) {
            $this->record->update(['email' => $newEmail]);

            Auth::guard('web')->logout();
            Session::invalidate();
            // Notification::make() (Filament) flash-nya tidak akan selamat lewat
            // Session::invalidate() di atas - pakai flash biasa yang dibaca auth.login.blade,
            // ditulis SETELAH invalidate supaya tidak ikut kehapus.
            Session::flash('status', 'Email berhasil diubah, silakan login lagi dengan email baru.');
            Session::regenerateToken();

            return redirect()->route('login');
        }

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
     * Lampiran bebas (bukan cuma KTP/KK) — karyawan tulis sendiri labelnya lalu pilih file, 1
     * baris baru per submit (bukan upsert per tipe seperti sebelumnya, karena tidak ada lagi
     * konsep tipe tetap). Kotak "Tambah Lampiran" di blade tetap sama baik saat daftar kosong
     * (jadi satu-satunya kotak yang tampil) maupun sudah ada lampiran lain (jadi kotak "tambah
     * lagi").
     */
    public function addProfileDocument(): void
    {
        abort_unless($this->record->id === Auth::id(), 403);

        $this->validate([
            'newDocumentLabel' => ['required', 'string', 'max:100'],
            'newDocumentFile' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        // Lampiran data diri (KTP/KK/dsb) disimpan TERENKRIPSI di disk privat 'local'
        // (storage/app/private, tidak web-accessible) — beda dari lampiran lain yang plaintext
        // di 'public'. Dilayani lewat route profil-saya.lampiran.show yang decrypt + cek izin.
        $ext = strtolower($this->newDocumentFile->getClientOriginalExtension() ?: $this->newDocumentFile->extension());
        $path = 'profile-documents/' . $this->record->id . '/' . Str::ulid() . '.' . $ext;

        Storage::disk('local')->put(
            $path,
            Crypt::encryptString(file_get_contents($this->newDocumentFile->getRealPath()))
        );

        UserProfileDocument::create([
            'user_id' => $this->record->id,
            'label' => $this->newDocumentLabel,
            'file_path' => $path,
        ]);

        $this->newDocumentLabel = '';
        $this->newDocumentFile = null;

        Notification::make()->title('Lampiran berhasil diunggah')->success()->send();
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

        Notification::make()->title('Lampiran dihapus')->success()->send();
    }

    /** Tab "Pembinaan" — karyawan itu sendiri, atasan langsung/hierarki-nya (instruksi user
     * 2026-09-26), ATAU pemegang permission modul Pembinaan Karyawan (SISTEM_PEMBINAAN_KARYAWAN).
     * Isinya CUMA Coaching — Counseling sengaja tidak ditampilkan di sini (instruksi user). */
    public function getCanViewPembinaanTabProperty(): bool
    {
        return $this->canViewMeritWidget
            || app(OrgHierarchyService::class)
                ->subordinates(Auth::user())
                ->whereKey($this->record->id)
                ->exists();
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

    // ── Transaksi Merit Point Modal ──
    public bool $showMeritTransactionModal = false;
    public string $meritTab = 'rule';
    public ?string $merit_rule_id = null;
    public string $merit_manual_type = 'add';
    public ?int $merit_points = null;
    public string $merit_reason = '';

    public function getCanCreateMeritTransactionProperty(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->can('Create:MeritPointTransaction')
            || $user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value)
            || PermissionHelper::hasGlobalRole($user);
    }

    public function getMeritPointRulesProperty()
    {
        $companyId = $this->record->company_id;

        return \App\Models\MeritPoint\MeritPointRule::query()
            ->where('is_active', true)
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

            $rule = \App\Models\MeritPoint\MeritPointRule::findOrFail($this->merit_rule_id);
            $points = $rule->points;
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
     * sekalipun atasan bisa buka Data Diri bawahannya untuk tab lain (lihat canManage()). */
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
        // di Dashboard) — bukan cuma atasan yang lihat bawahannya.
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

        $orientations = $this->record->orientations()->orderBy('start_date')->get();
        $contracts = $this->record->contracts()->with('employeeType')->orderBy('contract_start_date')->get();

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
                $tetapStart ??= $c->contract_start_date;
                $nodes[] = ['phase' => 'tetap', 'title' => 'Diangkat karyawan tetap', 'start' => $c->contract_start_date, 'end' => null, 'meta' => null];

                continue;
            }

            $kontrakCount++;
            $nodes[] = [
                'phase' => 'kontrak',
                'title' => $kontrakCount === 1 ? 'Kontrak pertama' : "Perpanjangan kontrak ke-{$kontrakCount}",
                'start' => $c->contract_start_date,
                'end' => $c->contract_end_date,
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
            $cur = $this->record->currentOrientation;
            $summary['headline'] = 'Orientasi';
            if ($cur?->end_date) {
                $summary['ended'] = Carbon::parse($cur->end_date);
                $summary['sub'] = 'Berakhir ' . $summary['ended']->translatedFormat('d M Y');
                $summary['progress'] = $cur->start_date ? $this->phaseProgress(Carbon::parse($cur->start_date), $summary['ended']) : null;
            }
        } elseif ($category === EmployeeTypeCategory::KONTRAK->value) {
            $cur = $this->record->currentContract;
            $summary['headline'] = $kontrakCount > 1 ? "Kontrak (perpanjangan ke-{$kontrakCount})" : 'Kontrak';
            if ($cur?->contract_end_date) {
                $summary['ended'] = Carbon::parse($cur->contract_end_date);
                $summary['sub'] = 'Berakhir ' . $summary['ended']->translatedFormat('d M Y');
                $summary['progress'] = $cur->contract_start_date ? $this->phaseProgress(Carbon::parse($cur->contract_start_date), $summary['ended']) : null;
            }
        } elseif ($category === EmployeeTypeCategory::TETAP->value) {
            // TMT resmi (user_profiles.tmt_tetap) diutamakan, fallback ke baris UserContract
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
            ->where('user_id', $this->record->id)
            ->where('is_paid', true)
            ->with('payrollPeriod')
            ->get()
            ->sortByDesc(fn (Payroll $p) => $p->payrollPeriod?->end_date)
            ->values();
    }

    public function getDocumentsProperty()
    {
        return UserDocuments::query()
            ->where('user_id', $this->record->id)
            ->orderByDesc('issued_date')
            ->get();
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
            : "MyStaff - {$this->record->name}";

        return view('livewire.employee-statistics')
            ->layout('components.layout', ['title' => $title]);
    }
}
