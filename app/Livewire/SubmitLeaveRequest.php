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

    /** Lampiran surat dokter/bidan — wajib untuk cuti khusus (haid/melahirkan/keguguran). */
    public array $attachments = [];

    public ?array $balance = null;
    public ?array $periode = null;

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
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
        $windowOpen = $service->cutiTahunanEligibleDate(Auth::user())?->subMonth();

        return collect(LeaveTypeCategoryEnum::cases())
            ->reject(function (LeaveTypeCategoryEnum $cat) use ($isFemale, $windowOpen, $tenureMet) {
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
        return app(AttendaceReqService::class)->getMinimumTenureBlockNote(Auth::user());
    }

    /** Kenapa Cuti Tahunan tidak muncul di dropdown (belum masuk jendela pengajuan) - null
     *  kalau memang tidak dibatasi atau jendelanya sudah buka. */
    public function getCutiTahunanClosedNoteProperty(): ?string
    {
        $eligibleDate = app(AttendaceReqService::class)->cutiTahunanEligibleDate(Auth::user());
        $windowOpen = $eligibleDate?->copy()->subMonth();

        if (! $windowOpen || now()->gte($windowOpen)) {
            return null;
        }

        return "Cuti Tahunan belum bisa diajukan - baru buka mulai {$windowOpen->translatedFormat('d M Y')} (1 bulan sebelum masa kerja minimal terpenuhi pada {$eligibleDate->translatedFormat('d M Y')}).";
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
        $this->refreshQuota();
    }

    public function updatedStartDate(): void
    {
        $this->refreshQuota();
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

    protected function rules(): array
    {
        return [
            'leave_type' => ['required', \Illuminate\Validation\Rule::enum(LeaveTypeCategoryEnum::class)],
            'start_date' => ['required', 'date'],
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
        ];
    }

    public function submit(): void
    {
        if ($this->submitted) {
            return;
        }

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

        try {
            app(AttendaceReqService::class)->storeLeaveRequest([
                'type_id' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'attachments' => $data['attachments'] ?? null,
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
