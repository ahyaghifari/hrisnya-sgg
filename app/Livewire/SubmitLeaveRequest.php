<?php

namespace App\Livewire;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Services\Attendance\AttendaceReqService;
use App\Services\Attendance\ScheduleResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmitLeaveRequest extends Component
{
    use WithFileUploads;

    public ?string $leave_type = null; // key LeaveTypeCategoryEnum
    public bool $submitted = false; // true sesudah sukses, sampai redirect selesai (cegah kirim ganda)
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $reason = null;
    private const MAX_SUBSTITUTES = 5;

    public bool $with_substitute = false;
    public array $substitute_user_ids = [];
    public ?string $substitute_task = null;

    public function toggleSubstitute(): void
    {
        $this->with_substitute = ! $this->with_substitute;

        // Nyala: mulai dari 1 baris kosong. Mati: buang semua pilihan.
        $this->substitute_user_ids = $this->with_substitute ? [''] : [];

        if (! $this->with_substitute) {
            $this->substitute_task = null;
        }
    }

    public function addSubstitute(): void
    {
        if (count($this->substitute_user_ids) < self::MAX_SUBSTITUTES) {
            $this->substitute_user_ids[] = '';
        }
    }

    public function removeSubstitute(int $index): void
    {
        unset($this->substitute_user_ids[$index]);
        $this->substitute_user_ids = array_values($this->substitute_user_ids) ?: [''];
    }

    /** Lampiran surat dokter/bidan — wajib untuk cuti khusus (haid/melahirkan/keguguran). */
    public array $attachments = [];

    public ?array $balance = null;
    public ?array $periode = null;

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    /** H-30 cuma berlaku untuk Cuti Tahunan (instruksi user 2026-09-28) - null kalau jenis cuti
     *  lain atau belum dipilih, dipakai sebagai `min` date picker (dikunci di form, lihat blade)
     *  DAN sumber after_or_equal di rules(). Angka tetap, lihat AttendaceReqService::LEAVE_MIN_LEAD_DAYS
     *  (satu-satunya tempat angka 30 didefinisikan, jangan duplikasi di sini). */
    public function getMinLeaveDateProperty(): ?string
    {
        if ($lead = $this->selectedCategory?->minLeadDays()) {
            return now()->addDays($lead)->toDateString();
        }

        return $this->leave_type === LeaveTypeCategoryEnum::TAHUNAN->value
            ? app(AttendaceReqService::class)->minLeaveDate()->toDateString()
            : null;
    }

    public function getLeaveTypesProperty()
    {
        $service = app(AttendaceReqService::class);
        $isFemale = Auth::user()->profile?->gender === 'perempuan';
        $tenureMet = $service->minimumTenureMet(Auth::user());

        // Jendela pengajuan Cuti Tahunan baru buka 1 bulan sebelum masa kerja minimal
        // terpenuhi (lihat AttendaceReqService::assertCutiTahunanEligibility) - sebelum itu
        // Cuti Tahunan disaring dari dropdown sama sekali, bukan cuma warning. null = tidak
        // dibatasi (setting 0/tidak diset, atau joining_date kosong).
        $windowOpen = $service->cutiTahunanEligibleDate(Auth::user())?->subMonths(1);

        return collect(LeaveTypeCategoryEnum::cases())
            ->reject(function (LeaveTypeCategoryEnum $cat) use ($isFemale, $windowOpen, $tenureMet) {
                // Cuti Haid disembunyikan dari pengajuan KHUSUS 2 company (RSU Syifa Medika
                // Banjarbaru & Barabai, lihat HasCompanyLabelOverride) - instruksi user
                // 2026-09-30. Company lain tetap bisa ajukan Cuti Haid normal.
                if ($cat === LeaveTypeCategoryEnum::HAID && LeaveTypeCategoryEnum::usesLabelOverride(Auth::user()->company_id)) {
                    return true;
                }

                // Cuti haid/melahirkan/keguguran cuma buat karyawan perempuan.
                if ($cat->isFemaleOnly() && ! $isFemale) {
                    return true;
                }

                if ($cat === LeaveTypeCategoryEnum::TAHUNAN) {
                    return $windowOpen && now()->lt($windowOpen);
                }

                // Jenis cuti lain (bukan Tahunan) - masa kerja minimal berlaku langsung, tidak
                // ada jendela "boleh diisi lebih awal" seperti Tahunan.
                return ! $tenureMet;
            })
            ->values();
    }

    /** Kenapa jenis cuti selain Tahunan kosong dari dropdown - masa kerja belum mencapai
     *  minimal kebijakan perusahaan (CUTI_MIN_MASA_KERJA_BULAN). Null kalau tidak dibatasi
     *  atau sudah terpenuhi. */
    public function getMinimumTenureNoteProperty(): ?string
    {
        // Jendela Cuti Tahunan sudah buka (masuk 1 bulan sebelum eligible) - form harus tetap
        // tampil walau masa kerja belum penuh, dropdown cuma berisi Cuti Tahunan.
        $eligibleDate = app(AttendaceReqService::class)->cutiTahunanEligibleDate(Auth::user());

        if ($eligibleDate && now()->gte($eligibleDate->copy()->subMonths(1))) {
            return null;
        }

        return app(AttendaceReqService::class)->getMinimumTenureBlockNote(Auth::user());
    }

    /** Kenapa Cuti Tahunan tidak muncul di dropdown (belum masuk jendela pengajuan) - null
     *  kalau memang tidak dibatasi atau jendelanya sudah buka. */
    public function getCutiTahunanClosedNoteProperty(): ?string
    {
        $eligibleDate = app(AttendaceReqService::class)->cutiTahunanEligibleDate(Auth::user());
        $windowOpen = $eligibleDate?->copy()->subMonths(1);

        if (! $windowOpen || now()->gte($windowOpen)) {
            return null;
        }

        return "Cuti Tahunan baru bisa diajukan mulai {$windowOpen->translatedFormat('d M Y')}, 1 bulan sebelum masa kerja minimal terpenuhi ({$eligibleDate->translatedFormat('d M Y')}).";
    }

    /** Kategori cuti khusus dari jenis cuti yang dipilih (null kalau cuti biasa/tahunan). */
    public function getSelectedCategoryProperty(): ?LeaveTypeCategoryEnum
    {
        return $this->leave_type ? LeaveTypeCategoryEnum::tryFrom($this->leave_type) : null;
    }

    public function getRequiresAttachmentProperty(): bool
    {
        return (bool) $this->selectedCategory?->requiresAttachment();
    }

    /**
     * Jumlah hari pengajuan — tanggal mulai == tanggal selesai = 1 hari. MELAHIRKAN/KEGUGURAN
     * (LeaveTypeCategoryEnum::countsCalendarDays()) dihitung hari KALENDER (UU 13/2003 Ps.82);
     * kategori lain dihitung hari kerja saja (off_day jadwal user tidak ikut, ScheduleResolver).
     * null kalau tanggal belum lengkap. Sama persis dengan AttendaceReqService::countLeaveDays()
     * yang jadi total_days saat submit.
     */
    public function getWorkingDaysProperty(): ?int
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        if ($this->end_date === $this->start_date) {
            return 1;
        }

        if ($this->selectedCategory?->countsCalendarDays()) {
            return \Carbon\Carbon::parse($this->start_date)->diffInDays(\Carbon\Carbon::parse($this->end_date)) + 1;
        }

        return app(ScheduleResolver::class)->workingDaysBetween(Auth::user(), $this->start_date, $this->end_date);
    }

    public function updatedLeaveType(): void
    {
        $this->attachments = [];
        $this->lockEndDate();
        $this->refreshQuota();
    }

    public function updatedStartDate(): void
    {
        $this->lockEndDate();
        $this->refreshQuota();
    }

    /** Jenis cuti dengan hari baku (lockedDays): tanggal selesai = mulai + N - 1, bukan input user. */
    private function lockEndDate(): void
    {
        $days = $this->selectedCategory?->lockedDays();

        if ($days && $this->start_date) {
            $this->end_date = \Carbon\Carbon::parse($this->start_date)->addDays($days - 1)->toDateString();
        }
    }

    /**
     * Sama seperti info kuota di halaman approval (§8) — kuota (LeaveTypeCategoryEnum::quotaDays())
     * DAN batas per-periode (setting cuti_tahunan_max_per_bulan, kalau leave type-nya kategori
     * Tahunan) ditampilkan ke pemohon SEBELUM submit, bukan cuma diketahui approver.
     */
    private function refreshQuota(): void
    {
        if (! $this->leave_type) {
            $this->balance = null;
            $this->periode = null;
            return;
        }

        $service = app(AttendaceReqService::class);

        $this->balance = $service->getLeaveUserBalance(Auth::id(), $this->leave_type, $this->start_date);
        $this->periode = $service->getCutiTahunanPeriodeUsage(Auth::user(), $this->leave_type, $this->start_date);
    }

    /** AttendanceSettingEnum::KETERANGAN_CUTI_IZIN_WAJIB - company-scoped, default OFF. Ditampilkan
     *  ke pemohon SEBELUM submit (asterisk + validasi form), enforcement sebenarnya tetap di
     *  AttendaceReqService::assertKeteranganWajibDiisi() (jaring pengaman server-side, dipakai
     *  juga oleh submit lewat mobile API). */
    public function getReasonRequiredProperty(): bool
    {
        return (bool) \App\Models\Setting::resolve(
            \App\Enum\Attendance\AttendanceSettingEnum::KETERANGAN_CUTI_IZIN_WAJIB->value,
            Auth::user()->company_id,
            default: false
        );
    }

    /** Calon pengganti: karyawan lain di unit yang sama, id => nama. */
    public function getSubstituteOptionsProperty()
    {
        return app(AttendaceReqService::class)->unitMates(Auth::user())->orderByName()->get()->pluck('name', 'id');
    }

    /** Kolom Pengganti cuma tampil di 2 company RS (sama pengecualian izin). */
    public function getSubstituteEnabledProperty(): bool
    {
        return app(AttendaceReqService::class)->leaveSubstituteEnabled(Auth::user());
    }

    protected function rules(): array
    {
        return [
            'substitute_user_ids' => ['nullable', 'array', 'max:' . self::MAX_SUBSTITUTES],
            'substitute_user_ids.*' => ['nullable', 'string'],
            'substitute_task' => ['nullable', 'string'],
            'leave_type' => ['required', \Illuminate\Validation\Rule::enum(LeaveTypeCategoryEnum::class)],
            'start_date' => array_filter([
                'required', 'date',
                $this->minLeaveDate ? 'after_or_equal:' . $this->minLeaveDate : null,
            ]),
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => [$this->reasonRequired ? 'required' : 'nullable', 'string'],
            'attachments' => [$this->requiresAttachment ? 'required' : 'nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function messages(): array
    {
        return [
            'attachments.required' => 'Jenis cuti ini wajib melampirkan surat dokter/bidan.',
            'reason.required' => 'Alasan/Keterangan wajib diisi.',
            'start_date.after_or_equal' => $this->selectedCategory?->minLeadDays()
                ? "{$this->selectedCategory->label()} wajib diajukan minimal {$this->selectedCategory->minLeadDays()} hari sebelum tanggal mulai."
                : 'Cuti Tahunan wajib diajukan minimal 30 hari sebelum tanggal mulai.',
        ];
    }

    public function submit(): void
    {
        if ($this->submitted) {
            return;
        }

        $this->lockEndDate(); // jaring server-side: request craft tidak bisa ubah end_date
        $data = $this->validate();

        // Guard server-side — jenis cuti female-only tidak boleh diajukan karyawan non-perempuan
        // (walau di dropdown sudah disembunyikan).
        $cat = LeaveTypeCategoryEnum::tryFrom($data['leave_type']);

        if ($cat?->isFemaleOnly() && Auth::user()->profile?->gender !== 'perempuan') {
            Notification::make()
                ->title('Jenis cuti ini hanya untuk karyawan perempuan.')
                ->danger()
                ->send();

            return;
        }

        // Cuti Haid disembunyikan KHUSUS 2 company (instruksi user 2026-09-30) - dropdown sudah
        // tidak menampilkannya buat company itu, dicek ulang di sini biar tidak bisa lolos lewat
        // request yang di-craft. Company lain tetap boleh.
        if ($cat === LeaveTypeCategoryEnum::HAID && LeaveTypeCategoryEnum::usesLabelOverride(Auth::user()->company_id)) {
            Notification::make()
                ->title('Cuti Haid tidak tersedia untuk perusahaan Anda.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(AttendaceReqService::class)->storeLeaveRequest([
                'type_id' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'] ?? null,
                'attachments' => $data['attachments'] ?? null,
                'substitute_user_ids' => $data['substitute_user_ids'] ?? [],
                'substitute_task' => $data['substitute_task'] ?? null,
            ], Auth::user());

            Notification::make()
                ->title('Pengajuan cuti berhasil dikirim')
                ->success()
                ->send();

            // Tombol tetap terkunci sampai halaman berpindah - respons sukses sudah selesai (loading
            // berakhir) tapi navigasi ke daftar pengajuan belum, jangan sampai bisa diklik lagi.
            $this->submitted = true;

            $this->redirect(route('my-attendance-requests'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal mengajukan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.submit-leave-request')->layout('components.layout', ['title' => 'Pengajuan Cuti']);
    }
}
