<?php

namespace App\Livewire\Pembinaan;

use App\Enum\CounselingStatus;
use App\Enum\Sp\SpReportStatus;
use App\Models\Coaching\Coaching;
use App\Models\Counseling\Counseling;
use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpReport;
use App\Models\User;
use App\Services\Counseling\CounselingService;
use App\Services\Sp\SpCaseList;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Daftar Pembinaan milik sendiri (Coaching + Counseling + Surat Peringatan, satu daftar, diberi label
 * jenis) untuk tab Pembinaan di Profil Saya, plus form ajukan counseling baru lewat modal
 * ("Konsul"). Reuse App\Services\Counseling\CounselingService::create() — sama service yang
 * dipakai App\Http\Controllers\Api\Counseling\CounselingController (mobile).
 *
 * Karyawan menunjuk sendiri "Konselor" (rekan kerja mana pun di company yang sama) saat
 * konsul, dan pilih sendiri apakah is_secret (default true) — kalau true, isi keluhan+tanggapan
 * cuma dia & konselor yang ditunjuk yang boleh baca; kalau false, siapa pun yang punya akses
 * modul Coaching & Counseling juga boleh baca (lihat Counseling::isContentVisibleTo()).
 */
class PembinaanSayaList extends Component
{
    public string $type = '';
    public bool $showConsultModal = false;
    public string $issue = '';
    public string $counselorId = '';
    public bool $isSecret = true;

    public function openConsultModal(): void
    {
        $this->issue = '';
        $this->counselorId = '';
        $this->isSecret = true;
        $this->showConsultModal = true;
    }

    public function closeConsultModal(): void
    {
        $this->showConsultModal = false;
    }

    public function getCounselorOptionsProperty()
    {
        return User::whereCompany(Auth::user()->company_id)
            ->where('id', '!=', Auth::id())
            ->where('is_active', true)
            ->orderByName()
            ->get()->pluck('name', 'id');
    }

    /**
     * Tombol "Konsul ke Saya" — cuma buat karyawan BIASA (bukan atasan, tidak lihat dropdown
     * "Coaching & Counseling" di row 2 karena itu sekarang gerbangnya murni "punya staff")
     * dan cuma kalau ADA konsul yang masih PENDING (nunggu ditanggapi) — bukan sekadar pernah
     * jadi konselor. Yang sudah DONE semua tidak perlu tombolnya lagi.
     */
    public function getShowInboxButtonProperty(): bool
    {
        return ! Auth::user()->canAccessKaryawanMenu()
            && Counseling::where('counselor_id', Auth::id())
                ->where('status', CounselingStatus::PENDING->value)
                ->exists();
    }

    public function submitConsult(): void
    {
        $data = $this->validate([
            'issue' => ['required', 'string', 'max:2000'],
            // 'exists' saja tidak cukup — dropdown UI sudah exclude diri sendiri, tapi payload
            // Livewire bisa ditembak manual. Tanpa Rule::notIn ini, karyawan bisa jadi konselor
            // buat dirinya sendiri (lolos ke DB, cuma keliru di sisi UI).
            'counselorId' => ['required', 'exists:users,id', Rule::notIn([Auth::id()])],
        ], [], ['counselorId' => 'konselor']);

        app(CounselingService::class)->create((object) [
            'issue' => $data['issue'],
            'counselor_id' => $data['counselorId'],
            'is_secret' => $this->isSecret,
        ]);

        $this->showConsultModal = false;
        Notification::make()->title('Konsul berhasil diajukan')->success()->send();
    }

    public function setType(string $type): void
    {
        $this->type = in_array($type, ['coaching', 'counseling', 'sp'], true) ? $type : '';
    }

    /**
     * Baris seragam (jenis, judul, keterangan, tanggal, status, url) supaya view cukup satu pola
     * daftar ala Pengajuan Absensi; tiap jenis cuma beda label/warna/ikon.
     */
    public function render()
    {
        $id = Auth::id();
        $rows = collect();

        if ($this->type === '' || $this->type === 'coaching') {
            foreach (Coaching::where('user_id', $id)->with('coachBy')->get() as $c) {
                $rows->push([
                    'type' => 'coaching', 'date' => $c->created_at, 'title' => $c->title,
                    'info' => 'Coach: ' . ($c->coachBy?->name ?? '-') . ($c->improvement_due_date ? ' · Deadline ' . Carbon::parse($c->improvement_due_date)->translatedFormat('d M Y') : ''),
                    'status' => $c->follow_up_status?->getLabel() ?? 'Berjalan',
                    'badge' => $c->follow_up_status ? 'badge-approved' : 'badge-pending',
                    'url' => route('coaching.show', $c),
                ]);
            }
        }

        if ($this->type === '' || $this->type === 'counseling') {
            foreach (Counseling::where('user_id', $id)->with(['respondedBy', 'counselor'])->get() as $c) {
                $rows->push([
                    'type' => 'counseling', 'date' => $c->created_at, 'title' => Str::limit($c->issue, 60),
                    'info' => 'Konselor: ' . ($c->counselor?->name ?? '-') . ($c->response ? ' · Ditanggapi ' . ($c->respondedBy?->name ?? $c->counselor?->name) : ''),
                    'status' => CounselingStatus::tryFrom($c->status)?->getLabel() ?? $c->status,
                    'badge' => $c->status === 'done' ? 'badge-approved' : 'badge-pending',
                    'url' => route('counseling.kelola.show', $c),
                ]);
            }
        }

        if ($this->type === '' || $this->type === 'sp') {
            // SP yang belum diterbitkan dan Berita Acara draft belum terlihat karyawan (instruksi user 2026-10-01/03).
            $sps = EmployeeSp::issued()->where('user_id', $id)->with(['user', 'report'])->get();
            $reports = SpReport::where('user_id', $id)
                ->whereNotNull('status')
                ->where('status', '!=', SpReportStatus::DRAFT->value)
                ->with(['user', 'sp.document'])
                ->get();
            // SP dari BA ini yang masih draft dianggap belum ada: baris tetap di tahap sebelumnya.
            $reports->each(fn ($r) => $r->sp && ! $r->sp->isIssued() ? $r->setRelation('sp', null) : null);

            foreach (SpCaseList::merge($reports, $sps) as $case) {
                $sp = $case['sp'];
                $report = $case['report'];
                $nomor = ($sp?->nomor ?? $report?->nomor);

                if ($sp) {
                    $active = ! $sp->isCancelled() && $sp->start_date->lte(today()) && ($sp->end_date === null || $sp->end_date->gte(today()));
                    $status = $sp->isCancelled() ? 'Dibatalkan' : ($active ? 'Aktif' : 'Expired');
                    $badge = $active ? 'badge-pending' : 'badge-cancelled';
                } else {
                    $status = $report->keputusan?->getLabel() ?? $report->status->getLabel();
                    $badge = $report->status === SpReportStatus::SELESAI ? 'badge-approved' : 'badge-pending';
                }

                $rows->push([
                    'type' => 'sp', 'date' => $case['date'],
                    'title' => $sp ? $sp->sp_level->getLabel() : 'Berita Acara',
                    'info' => $nomor ? 'No. ' . $nomor : ($sp ? '' : 'Proses Surat Peringatan'),
                    'status' => $status, 'badge' => $badge,
                    'url' => $sp ? route('sp.saya.show', $sp) : route('sp.saya.ba', $report),
                ]);
            }
        }

        return view('livewire.pembinaan.pembinaan-saya-list', ['rows' => $rows->sortByDesc('date')->values()]);
    }
}
