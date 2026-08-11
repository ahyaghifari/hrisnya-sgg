<?php

namespace App\Livewire;

use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\EmployeeTypeCategory;
use App\Exports\Attendance\AttendanceHistoryExport;
use App\Livewire\Concerns\HandlesProfilePhotoUpload;
use App\Livewire\Concerns\ResolvesTodayAttendanceStatus;
use App\Models\AuditLog;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Religions;
use App\Models\Payroll\Payroll;
use App\Models\User;
use App\Models\User\UserDocuments;
use App\Models\User\UserProfileDocument;
use App\Services\Attendance\AttendaceReqService;
use App\Services\Attendance\AttendancePeriodResolver;
use App\Services\OrgHierarchyService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeAttendanceStatistics extends Component
{
    use ResolvesTodayAttendanceStatus;
    use HandlesProfilePhotoUpload;
    use WithFileUploads;

    public User $record;

    public ?string $date_from = null;
    public ?string $date_to = null;
    public string $status = 'approved';

    public string $calendarMonth;

    // ── Edit Data Diri (self-service, lihat saveProfil()) ──
    public bool $editingProfil = false;
    public ?string $email = null;
    public ?string $nickname = null;
    public ?string $gender = null;
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
        // Route /data-diri tidak punya segment {record} — otomatis pakai user yang login.
        $record ??= Auth::user();

        abort_unless($this->canManage($record), 403);

        $this->record = $record->load([
            'company',
            'roles',
            'profile.religion',
            'profile.orgStructure.parent.parent',
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

        $path = $this->newDocumentFile->store('profile-documents/' . $this->record->id, 'public');

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

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        Notification::make()->title('Lampiran dihapus')->success()->send();
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

    /**
     * Gabungan riwayat User + UserProfiles (LogsFieldAuditChanges di kedua model menulis ke
     * `audit_logs` dengan key berprefix user_id yang sama) — 1 query cukup, apa pun tabel asal
     * fieldnya. Dibatasi 100 baris terakhir, cukup untuk kebutuhan "siapa mengubah apa
     * terakhir" tanpa perlu pagination di versi pertama ini.
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
            ->limit(100)
            ->get();
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

        // HC/Kepegawaian company-wide (bukan cuma atasan lihat bawahan) — dibatasi ke
        // company sendiri, konsisten dengan scoping EmployeeResource di admin.
        if ($user->can(\App\Enum\CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value) && $user->company_id === $record->company_id) {
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

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [
            'status' => $this->status !== 'all' ? $this->status : null,
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

        $requests = AttendanceRequests::query()
            ->where('user_id', $this->record->id)
            ->whereIn('status', $activeStatuses)
            ->whereIn('type', ['leave', 'permission', 'overtime'])
            ->with(['leaveDetails', 'permissionDetails', 'overtimeDetails'])
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

            foreach ($request->overtimeDetails as $detail) {
                $date = Carbon::parse($detail->date);
                if ($date->between($start, $end)) {
                    $addMarker($date->toDateString(), 'overtime', $request->status);
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

    public function export()
    {
        $rows = $this->requests->map(fn (AttendanceRequests $r) => [
            'date' => $r->created_at->translatedFormat('d M Y H:i'),
            'type' => ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$r->type] ?? $r->type,
            'keterangan' => $this->keterangan($r),
            'status' => AttendanceReqStatus::from($r->status)->getLabel(),
        ])->toArray();

        $periodLabel = "{$this->date_from} s/d {$this->date_to}";

        return Excel::download(
            new AttendanceHistoryExport($rows, $this->record->name, $periodLabel),
            'riwayat-absensi-' . str($this->record->name)->slug() . '.xlsx'
        );
    }

    public function render()
    {
        $title = $this->record->id === Auth::id()
            ? 'Data Diri'
            : "MyStaff - {$this->record->name}";

        return view('livewire.employee-attendance-statistics')
            ->layout('components.layout', ['title' => $title]);
    }
}
